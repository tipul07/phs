<?php
namespace phs\traits;

/**
 * Add status management methods for models which implement a status static array
 * @static $STATUSES_ARR
 */
trait PHS_Model_Trait_statuses
{
    public function get_statuses(null | bool | string $lang = false) : array
    {
        static $statuses_arr = [];

        if (empty(static::$STATUSES_ARR)) {
            return [];
        }

        if (empty($lang)
            && !empty($statuses_arr)) {
            return $statuses_arr;
        }

        $result_arr = $this->translate_array_keys(self::$STATUSES_ARR, ['title'], $lang);

        if (empty($lang)) {
            $statuses_arr = $result_arr;
        }

        return $result_arr;
    }

    public function get_statuses_as_key_val(null | bool | string $lang = false) : array
    {
        static $statuses_key_val_arr = null;

        if (empty($lang)
            && $statuses_key_val_arr !== null) {
            return $statuses_key_val_arr;
        }

        $key_val_arr = [];
        if (($statuses = $this->get_statuses($lang))) {
            foreach ($statuses as $key => $val) {
                if (!is_array($val)) {
                    continue;
                }

                $key_val_arr[$key] = $val['title'];
            }
        }

        if (empty($lang)) {
            $statuses_key_val_arr = $key_val_arr;
        }

        return $key_val_arr;
    }

    public function valid_status(?int $status, null | bool | string $lang = false) : ?array
    {
        $all_statuses = $this->get_statuses($lang);

        return $all_statuses[$status] ?? null;
    }

    public function get_status_title(?int $status, null | bool | string $lang = false) : ?string
    {
        return ($status_arr = $this->valid_status($status, $lang))
            ? ($status_arr['title'] ?? null)
            : null;
    }
}
