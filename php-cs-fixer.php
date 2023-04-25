<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$rules = [
    'php_unit_method_casing' => [
        'case' => 'snake_case',
    ],
    'ordered_class_elements' => [
        'order' => ['use_trait', 'public', 'protected', 'private', 'case',
            'constant', 'constant_public', 'constant_protected', 'constant_private',
            'property', 'property_public', 'property_protected', 'property_private',
            'property_public_readonly', 'property_protected_readonly', 'property_private_readonly',
            'property_public_static', 'property_protected_static', 'property_private_static',
            'construct', 'destruct',
            'method_abstract',
            'method_public_abstract', 'method_protected_abstract', 'method_private_abstract',
            'method_public_abstract_static', 'method_protected_abstract_static', 'method_private_abstract_static',
            'method', 'method_static', 'method_public', 'method_protected', 'method_private', 'method_public_static', 'method_protected_static', 'method_private_static',
            'magic', 'phpunit', ],
        'sort_algorithm' => 'none',
    ],
    'function_declaration' => [
        'closure_function_spacing' => 'none',
    ],
    'function_typehint_space' => true,
    'method_argument_space'   => [
        'keep_multiple_spaces_after_comma' => false,
        'on_multiline'                     => 'ignore',
    ],
    'single_space_after_construct' => [
        'constructs' => [
            'abstract', 'as', 'attribute', 'break', 'case', 'catch', 'class', 'clone', 'comment', 'const',
            'const_import', 'continue', 'do', 'echo', 'else', 'elseif', 'enum', 'extends', 'final', 'finally',
            'for', 'foreach', 'function', 'function_import', 'global', 'goto', 'if', 'implements', 'include',
            'include_once', 'instanceof', 'insteadof', 'interface', 'match', 'named_argument', 'namespace',
            'new', 'open_tag_with_echo', 'php_doc', 'php_open', 'print', 'private', 'protected', 'public',
            'readonly', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try',
            'type_colon', 'use', 'use_lambda', 'use_trait', 'var', 'while', 'yield', 'yield_from',
        ],
    ],

    'statement_indentation'       => true,
    'method_chaining_indentation' => true,
    'explicit_string_variable'    => true,
    'types_spaces'                => [
        'space' => 'single', 'space_multiple_catch' => 'single',
    ],
    'align_multiline_comment' => [
        'comment_type' => 'phpdocs_like',
    ],
    'ternary_to_null_coalescing' => true,
    'standardize_increment'      => true,
    'operator_linebreak'         => [
        'only_booleans' => false,
        'position'      => 'beginning',
    ],
    'not_operator_with_space'           => false,
    'not_operator_with_successor_space' => false,
    'no_space_around_double_colon'      => true,
    'logical_operators'                 => true,
    'binary_operator_spaces'            => [
        'default'   => 'single_space',
        'operators' => ['=>' => 'align_single_space_minimal'],
    ],
    'assign_null_coalescing_to_coalesce_equal' => true,
    'list_syntax'                              => ['syntax' => 'short'],
    'is_null'                                  => true,
    'return_type_declaration'                  => [
        'space_before' => 'one',
    ],
    'nullable_type_declaration_for_default_null_value' => true,
    'no_superfluous_elseif'                            => true,
    'no_alternative_syntax'                            => ['fix_non_monolithic_code' => true, ],
    'empty_loop_condition'                             => ['style' => 'while', ],
    'empty_loop_body'                                  => ['style' => 'semicolon', ],
    'control_structure_continuation_position'          => [
        'position' => 'same_line',
    ],
    'control_structure_braces'                => true,
    'single_line_comment_spacing'             => true,
    'no_empty_comment'                        => false,
    'comment_to_phpdoc'                       => true,
    'single_trait_insert_per_statement'       => false,
    'no_null_property_initialization'         => true,
    'no_unset_cast'                           => true,
    'modernize_types_casting'                 => false,
    'native_function_type_declaration_casing' => true,
    'integer_literal_case'                    => true,
    'no_multiple_statements_per_line'         => true,
    'class_reference_name_casing'             => true,
    'array_indentation'                       => true,
    'array_syntax'                            => ['syntax' => 'short'],
    'blank_line_between_import_groups'        => true,
    'no_blank_lines_before_namespace'         => true,
    'blank_line_after_namespace'              => true,
    'blank_line_after_opening_tag'            => true,
    'blank_line_before_statement'             => [
        'statements' => ['return', 'switch'],
    ],
    'braces' => [
        'allow_single_line_anonymous_class_with_empty_body' => false,
        'allow_single_line_closure'                         => false,
        'position_after_functions_and_oop_constructs'       => 'next',
        'position_after_control_structures'                 => 'same',
        'position_after_anonymous_constructs'               => 'same',
    ],
    'curly_braces_position' => [
        'control_structures_opening_brace'          => 'same_line',
        'functions_opening_brace'                   => 'next_line_unless_newline_at_signature_end',
        'classes_opening_brace'                     => 'next_line_unless_newline_at_signature_end',
        'anonymous_classes_opening_brace'           => 'same_line',
        'allow_single_line_empty_anonymous_classes' => false,
        'allow_single_line_anonymous_functions'     => true,
    ],
    'cast_spaces'                 => ['space' => 'none'],
    'class_attributes_separation' => [
        'elements' => [
            'const'        => 'one',
            'method'       => 'one',
            'property'     => 'one',
            'trait_import' => 'none',
        ],
    ],
    'class_definition' => [
        'multi_line_extends_each_single_line' => false,
        'single_item_single_line'             => false,
        'single_line'                         => false,
        'space_before_parenthesis'            => true,
        'inline_constructor_arguments'        => false,
    ],
    'concat_space' => [
        'spacing' => 'none',
    ],
    'constant_case'                          => ['case' => 'lower'],
    'declare_parentheses'                    => false,
    'declare_equal_normalize'                => ['space' => 'single'],
    'no_useless_else'                        => true,
    'elseif'                                 => true,
    'encoding'                               => true,
    'full_opening_tag'                       => true,
    'fully_qualified_strict_types'           => true, // added by Shift
    'general_phpdoc_tag_rename'              => true,
    'heredoc_to_nowdoc'                      => true,
    'include'                                => true,
    'increment_style'                        => ['style' => 'post'],
    'indentation_type'                       => true,
    'linebreak_after_opening_tag'            => true,
    'line_ending'                            => true,
    'lowercase_cast'                         => true,
    'lowercase_keywords'                     => true,
    'lowercase_static_reference'             => true, // added from Symfony
    'magic_method_casing'                    => true, // added from Symfony
    'magic_constant_casing'                  => true,
    'multiline_whitespace_before_semicolons' => [
        'strategy' => 'no_multi_line',
    ],
    'native_function_casing' => true,
    'no_alias_functions'     => true,
    'no_extra_blank_lines'   => [
        'tokens' => [
            'extra', 'throw', 'use', 'continue', 'curly_brace_block', 'parenthesis_brace_block',
            'return', 'square_brace_block', 'throw',
        ],
    ],
    'no_blank_lines_after_class_opening'  => true,
    'no_blank_lines_after_phpdoc'         => true,
    'no_closing_tag'                      => true,
    'no_empty_phpdoc'                     => true,
    'phpdoc_add_missing_param_annotation' => [
        'only_untyped' => false,
    ],
    'no_empty_statement'              => true,
    'no_leading_import_slash'         => true,
    'no_leading_namespace_whitespace' => true,
    'no_mixed_echo_print'             => [
        'use' => 'echo',
    ],
    'no_multiline_whitespace_around_double_arrow' => true,
    'no_short_bool_cast'                          => true,
    'no_singleline_whitespace_before_semicolons'  => true,
    'no_spaces_after_function_name'               => true,
    'semicolon_after_instruction'                 => true,
    'no_spaces_around_offset'                     => [
        'positions' => ['inside', 'outside'],
    ],
    'no_spaces_inside_parenthesis' => true,
    // Deprecated
    // 'no_trailing_comma_in_list_call'  => true,
    'no_trailing_comma_in_singleline' => [
        'elements' => [], // ['arguments', 'array_destructuring', 'array', 'group_import'],
    ],
    // Deprecated
    // 'no_trailing_comma_in_singleline_array' => false,
    'no_trailing_whitespace'            => true,
    'no_trailing_whitespace_in_comment' => true,
    'no_unneeded_control_parentheses'   => [
        'statements' => ['break', 'clone', 'continue', 'echo_print', 'return', 'switch_case', 'yield'],
    ],
    'no_unreachable_default_argument_value'         => true,
    'simplified_if_return'                          => true,
    'no_useless_return'                             => true,
    'no_whitespace_before_comma_in_array'           => true,
    'no_whitespace_in_blank_line'                   => true,
    'normalize_index_brace'                         => true,
    'object_operator_without_whitespace'            => true,
    'ordered_imports'                               => ['sort_algorithm' => 'length', 'imports_order' => ['const', 'class', 'function']],
    'psr_autoloading'                               => false,
    'phpdoc_indent'                                 => true,
    'phpdoc_inline_tag_normalizer'                  => true,
    'phpdoc_no_access'                              => true,
    'phpdoc_no_package'                             => true,
    'phpdoc_no_useless_inheritdoc'                  => true,
    'phpdoc_scalar'                                 => true,
    'phpdoc_single_line_var_spacing'                => true,
    'phpdoc_summary'                                => false,
    'phpdoc_to_comment'                             => false, // override to preserve user preference
    'phpdoc_tag_type'                               => true,
    'phpdoc_trim'                                   => true,
    'phpdoc_trim_consecutive_blank_line_separation' => true,
    'phpdoc_types'                                  => true,
    'phpdoc_var_without_name'                       => true,
    'phpdoc_no_empty_return'                        => true,
    'phpdoc_align'                                  => ['align' => 'left'],
    'phpdoc_line_span'                              => ['property' => 'single', 'const' => 'single', 'method' => 'multi'],
    'phpdoc_order'                                  => ['order' => ['param', 'return', 'throws']],
    'phpdoc_types_order'                            => ['sort_algorithm' => 'none', 'null_adjustment' => 'always_first'],
    'phpdoc_var_annotation_correct_order'           => true,
    'self_accessor'                                 => true,
    'self_static_accessor'                          => true,
    'short_scalar_cast'                             => true,
    'compact_nullable_typehint'                     => true,
    'simplified_null_return'                        => false, // disabled as "risky"
    'single_blank_line_at_eof'                      => true,
    // 'single_class_element_per_statement' => [
    //      'elements' => [],//['const', 'property'],
    // ],
    'single_import_per_statement' => true,
    'single_line_after_imports'   => true,
    'single_line_comment_style'   => [
        'comment_types' => ['hash'],
    ],
    // VERY risky...
    // 'single_quote' => ['strings_containing_single_quote_chars' => true],
    'space_after_semicolon'          => true,
    'standardize_not_equals'         => true,
    'switch_case_semicolon_to_colon' => true,
    'switch_case_space'              => true,
    'ternary_operator_spaces'        => true,
    'trailing_comma_in_multiline'    => ['elements' => ['arrays']],
    'trim_array_spaces'              => true,
    'unary_operator_spaces'          => true,
    'visibility_required'            => [
        'elements' => ['method', 'property', 'const'],
    ],
    'whitespace_after_comma_in_array' => true,
];

$finder = Finder::create()
    ->in([
        __DIR__,
    ])
    ->name('*.php')
    ->exclude(['vendor', 'plugins', 'contexts', 'features', '_uploads', 'system/logs'])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setFinder($finder)
    ->setRules($rules)
    ->setRiskyAllowed(true)
    ->setUsingCache(true);
