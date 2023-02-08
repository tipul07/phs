<?php
namespace phs\traits;

/**
 * Add status management methods for models which implement a status static array
 * @static $STATUSES_ARR
 */
trait PHS_Model_Trait_statuses
{
    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_statuses($lang = false) : array
    {
        static $statuses_arr = [];

        if (empty(static::$STATUSES_ARR)) {
            return [];
        }

        if ($lang === false
         && !empty($statuses_arr)) {
            return $statuses_arr;
        }

        $result_arr = $this->translate_array_keys(self::$STATUSES_ARR, ['title'], $lang);

        if ($lang === false) {
            $statuses_arr = $result_arr;
        }

        return $result_arr;
    }

    /**
     * @param false|string $lang
     *
     * @return array
     */
    public function get_statuses_as_key_val($lang = false) : array
    {
        static $statuses_key_val_arr = null;

        if ($lang === false
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

        if ($lang === false) {
            $statuses_key_val_arr = $key_val_arr;
        }

        return $key_val_arr;
    }

    /**
     * @param int|string $status
     * @param false|string $lang
     *
     * @return false|array
     */
    public function valid_status($status, $lang = false)
    {
        $all_statuses = $this->get_statuses($lang);
        if (empty($status)
         || !isset($all_statuses[$status])) {
            return false;
        }

        return $all_statuses[$status];
    }
}
