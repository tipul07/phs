<?php
namespace phs\libraries;

use Exception;
use ReflectionClass;
use ReflectionProperty;
use phs\system\core\attributes\PHS_Dependency;

abstract class PHS_Has_dependencies extends PHS_Registry
{
    private static array $_instances = [];

    private static array $_lazy_load = [];

    private static array $_dependency_errors = [];

    protected function _check_dependencies_properties() : void
    {
        $this->reset_error();

        try {
            $all_dependencies = [];
            $properties = ($properties = $this->_get_all_properties()) ? array_values($properties) : [];
            foreach ($properties as $property) {
                if (!($attributes = $property->getAttributes(PHS_Dependency::class))) {
                    continue;
                }

                foreach ($attributes as $attribute) {
                    /** @var PHS_Dependency $instance */
                    $instance = $attribute->newInstance();

                    $all_dependencies[$instance->priority] ??= [];

                    $all_dependencies[$instance->priority][] = [
                        'prop_obj'       => $property,
                        'class'          => $property->getType()?->getName(),
                        'as_singleton'   => $instance->as_singleton,
                        'error_if_fails' => $instance->error_if_fails,
                        'depends_on'     => $instance->depends_on,
                    ];
                }
            }

            if (!$all_dependencies) {
                return;
            }

            ksort($all_dependencies);

            foreach ($all_dependencies as $dependencies) {
                if (!$dependencies) {
                    continue;
                }

                foreach ($dependencies as $dependency) {
                    if (!($prop_obj = $dependency['prop_obj'])
                       || !($phs_class = $dependency['class'])) {
                        $this->_set_dependency_error(
                            self::_t('Error for field %s in class %s, dependency %s.',
                                $prop_obj->getName(),
                                $prop_obj->getDeclaringClass()?->getName() ?? 'N/A',
                                $phs_class ?? 'N/A')
                        );

                        if (!empty($phs_class)) {
                            self::_update_lazy_loaders($phs_class, null);
                        }

                        return;
                    }

                    /** @var string|PHS_Instantiable $phs_class */
                    if (!($details = PHS_Instantiable::extract_details_from_full_namespace_name($phs_class))) {
                        $this->_set_dependency_error(
                            self::_t('Do not use %s attribute on non-PHS instantiable or library classes.', PHS_Dependency::class)
                        );

                        return;
                    }

                    $is_library = empty($details['instance_type']);

                    if ($dependency['as_singleton']
                       && ($instance_obj = self::_get_instance_for_full_class_with_namespace($phs_class))) {
                        $prop_obj->setValue($this, $instance_obj);
                        continue;
                    }

                    $in_queue = self::_lazy_loader_in_queue($phs_class, $this, $prop_obj->getName());

                    self::_add_lazy_loader_to_queue($phs_class, $this, $prop_obj, $dependency['as_singleton']);

                    if ($in_queue) {
                        continue;
                    }

                    $args = ['as_singleton' => $dependency['as_singleton']];
                    if (!$is_library) {
                        $args['full_class_name'] = $phs_class;
                    }

                    if (!($instance_obj = $phs_class::get_instance(...$args))
                       && $dependency['error_if_fails']) {
                        $this->_set_dependency_error(
                            self::_t('Error loading required resources: %s', $phs_class)
                        );

                        self::_update_lazy_loaders($phs_class, null);
                        $prop_obj->setValue($this, null);

                        return;
                    }

                    $has_depency_error = $instance_obj::has_dependency_errors();
                    if ($dependency['error_if_fails']
                        && ($has_depency_error || $instance_obj->has_error())
                    ) {
                        $error_msg = self::_t('Error loading class %s', $instance_obj::class);
                        if ($instance_obj->has_error()) {
                            $error_msg .= ' '.self::_t('ERROR: %s', $instance_obj->get_simple_error_message());
                        }
                        if ($has_depency_error) {
                            $error_msg .= ' '.self::_t('ERROR: %s', implode('; ', $instance_obj::get_dependency_errors()));
                        }

                        $this->_set_dependency_error($error_msg);

                        return;
                    }

                    self::_update_lazy_loaders($phs_class, $instance_obj);

                    $prop_obj->setValue($this, $instance_obj);
                }
            }
        } catch (Exception $e) {
            $this->set_error(
                self::ERR_DEPENDENCIES,
                self::_t('Exception when loading required resources: %s', $e->getMessage())
            );
        }
    }

    private function _get_all_properties(?ReflectionClass $obj = null) : ?array
    {
        if ($obj === null) {
            $obj = new ReflectionClass($this);
        }

        try {
            $all_properties = [];
            if (($properties = $obj->getProperties())) {
                foreach ($properties as $property) {
                    $all_properties[$property->class.'::'.$property->getName()] = $property;
                }
            }

            if (($parent = $obj->getParentClass())
               && ($parent_properties = $this->_get_all_properties($parent))) {
                $all_properties = array_merge($all_properties, $parent_properties);
            }

            return $all_properties;
        } catch (Exception $e) {
            return null;
        }
    }

    private function _set_dependency_error(string $msg) : void
    {
        self::$_dependency_errors[$this::class] ??= [];
        self::$_dependency_errors[$this::class][] = 'Dependency error: '.$msg;
    }

    public static function get_dependency_errors(?string $class = null) : ?array
    {
        $class ??= static::class;

        return self::$_dependency_errors[ltrim($class, '\\')] ?? null;
    }

    public static function has_dependency_errors(?string $class = null) : bool
    {
        $class ??= static::class;

        return !empty(self::$_dependency_errors[ltrim($class, '\\')]);
    }

    final protected static function _set_instance_for_full_class_with_namespace(
        string $full_class_name,
        null | PHS_Instantiable | PHS_Library $instance_obj
    ) : void {
        self::$_instances[ltrim($full_class_name, '\\')] = $instance_obj;
    }

    final protected static function _get_instance_for_full_class_with_namespace(
        string $full_class_name
    ) : null | PHS_Instantiable | PHS_Library {
        return self::$_instances[ltrim($full_class_name, '\\')] ?? null;
    }

    private static function _add_lazy_loader_to_queue(
        string $class_to_load,
        PHS_Instantiable | PHS_Library $request_class,
        ReflectionProperty $property,
        bool $as_singleton,
    ) : void {
        self::$_lazy_load[$class_to_load][$request_class::class][$property->getName()][$as_singleton ? 1 : 0][] = [
            'request_class' => $request_class,
            'property'      => $property,
        ];
    }

    private static function _lazy_loader_in_queue(
        string $class_to_load,
        PHS_Instantiable | PHS_Library $request_class,
        string $property_name,
    ) : bool {
        return !empty(self::$_lazy_load[$class_to_load][$request_class::class][$property_name]);
    }

    private static function _update_lazy_loaders(
        string $full_class_name,
        null | PHS_Instantiable | PHS_Library $instance_obj
    ) : void {
        if (empty(self::$_lazy_load[$full_class_name])) {
            return;
        }

        foreach (self::$_lazy_load[$full_class_name] as $request_class => $lazy_props) {
            foreach ($lazy_props as $prop_name => $instance_types) {
                foreach ($instance_types as $as_singleton => $props) {
                    foreach ($props as $prop) {
                        if (empty($prop['property']) || empty($prop['request_class'])) {
                            continue;
                        }

                        $prop['property']->setValue($prop['request_class'], $instance_obj);
                    }
                }

                if (isset(self::$_lazy_load[$full_class_name][$request_class][$prop_name][0])) {
                    unset(self::$_lazy_load[$full_class_name][$request_class][$prop_name][0]);
                }
            }
        }
    }
}
