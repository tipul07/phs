<?php
namespace phs\system\core\views;

class PHS_View_email extends PHS_View
{
    public function email_vars() : array
    {
        if (($email_vars = $this->view_var('hook_args')['email_vars'] ?? null)
           && is_array($email_vars)) {
            return $email_vars;
        }

        return $this->view_var('email_vars') ?: [];
    }

    public function email_var(string $key, string $default = '') : mixed
    {
        return $this->email_vars()[$key] ?? $default;
    }

    public static function init_view(string | array $template, array $params = []) : ?self
    {
        $params['view_class'] = 'email';

        return parent::init_view($template, $params);
    }
}
