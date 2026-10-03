<?php
namespace phs\setup\libraries;

class PHS_Setup_layout extends PHS_Setup_view
{
    private array $common_data;

    private array $errors_arr = [];

    private array $success_arr = [];

    private array $notices_arr = [];

    private static ?self $layout_instance_obj = null;

    public function __construct()
    {
        parent::__construct();

        if (@class_exists(PHS_Setup::class, false)) {
            $this->common_data = [
                'phs_setup_obj' => PHS_Setup::get_instance(),
            ];
        } else {
            $this->common_data = [
                'phs_setup_obj' => null,
            ];
        }
    }

    public function has_error_msgs(): bool
    {
        return !empty($this->errors_arr);
    }

    public function has_success_msgs(): bool
    {
        return !empty($this->errors_arr);
    }

    public function has_notices_msgs(): bool
    {
        return !empty($this->notices_arr);
    }

    public function reset_error_msgs(): void
    {
        $this->errors_arr = [];
    }

    public function add_error_msg(string $msg): void
    {
        $this->errors_arr[] = $msg;
    }

    public function reset_success_msgs(): void
    {
        $this->success_arr = [];
    }

    public function add_success_msg(string $msg): void
    {
        $this->success_arr[] = $msg;
    }

    public function reset_notice_msgs(): void
    {
        $this->notices_arr = [];
    }

    public function add_notice_msg(string $msg): void
    {
        $this->notices_arr[] = $msg;
    }

    public function render(string $template, array $data = [], bool $include_main_template = false): string
    {
        $this->set_context($this->common_data);

        // make errors available in template too
        if (!empty($this->errors_arr) || !empty($this->success_arr) || !empty($this->notices_arr)) {
            $data['notifications'] = [];
            if (!empty($this->errors_arr)) {
                $data['notifications']['error'] = $this->errors_arr;
            }
            if (!empty($this->success_arr)) {
                $data['notifications']['success'] = $this->success_arr;
            }
            if (!empty($this->notices_arr)) {
                $data['notifications']['notice'] = $this->notices_arr;
            }
        }

        $template_buf = $this->render_view($template, $data) ?: '';

        if (!$include_main_template) {
            return $template_buf;
        }

        $main_template_data = $data;
        $main_template_data['page_content'] = $template_buf;

        $this->set_context($main_template_data);

        return $this->render_view('template_main') ?: '';
    }

    public function get_common_data(?string $key = null): mixed
    {
        if ($key === false) {
            return $this->common_data;
        }

        return $this->common_data[$key] ?? null;
    }

    public function set_full_common_data(array $arr, bool $merge = false): void
    {
        $this->common_data = !$merge
            ? $arr
            : PHS_Setup_utils::merge_array_assoc($this->common_data, $arr);
    }

    public function set_common_data(string | array $key, mixed $val = null): bool
    {
        if ($val === null) {
            if (!is_array($key)) {
                return false;
            }

            foreach ($key as $kkey => $kval) {
                if (!is_scalar($kkey)) {
                    continue;
                }

                $this->common_data[$kkey] = $kval;
            }

            return true;
        }

        if (!is_scalar($key)) {
            return false;
        }

        $this->common_data[$key] = $val;

        return true;
    }

    public static function get_instance(): self
    {
        if (self::$layout_instance_obj !== null) {
            return self::$layout_instance_obj;
        }

        self::$layout_instance_obj = new self();

        return self::$layout_instance_obj;
    }
}
