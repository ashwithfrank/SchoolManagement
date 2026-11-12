<?php
namespace App\Core;

class View
{
    /** Render templates/<name>.php inside the application layout. */
    public static function page(string $template, array $data = [], string $title = ''): void
    {
        $content = self::partial($template, $data);
        $pageTitle = $title;
        $flashes = Flash::pull();
        require TEMPLATE_DIR . '/layouts/app.php';
    }

    /** Render a template without the layout (print views, partials). */
    public static function partial(string $template, array $data = []): string
    {
        $file = TEMPLATE_DIR . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** Standalone printable page (receipts, reports). */
    public static function print(string $template, array $data = [], string $title = ''): void
    {
        $content = self::partial($template, $data);
        $pageTitle = $title;
        require TEMPLATE_DIR . '/layouts/print.php';
    }
}
