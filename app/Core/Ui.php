<?php
namespace App\Core;

/** Tiny HTML helpers that always escape their input. */
class Ui
{
    public static function options(array $rows, string $valueKey, string $labelKey, $selected = null, ?string $blank = null): string
    {
        $h = $blank !== null ? '<option value="">' . e($blank) . '</option>' : '';
        foreach ($rows as $r) {
            $h .= '<option value="' . e($r[$valueKey]) . '"' . selected($r[$valueKey], $selected) . '>' . e($r[$labelKey]) . '</option>';
        }
        return $h;
    }

    public static function sectionOptions(array $sections, $selected = null, ?string $blank = 'All sections'): string
    {
        $h = $blank !== null ? '<option value="">' . e($blank) . '</option>' : '';
        foreach ($sections as $s) {
            $h .= '<option value="' . e($s['id']) . '" data-class="' . e($s['class_id']) . '"' . selected($s['id'], $selected) . '>' . e($s['class_name'] . ' - ' . $s['name']) . '</option>';
        }
        return $h;
    }

    public static function badge(string $text, string $type = 'secondary'): string
    {
        return '<span class="badge text-bg-' . e($type) . '">' . e($text) . '</span>';
    }

    public static function status(bool $active): string
    {
        return $active ? self::badge('Active', 'success') : self::badge('Inactive', 'secondary');
    }

    /** Current query string with overrides. */
    public static function query(array $override = []): string
    {
        $q = array_merge($_GET, $override);
        foreach ($q as $k => $v) {
            if ($v === null || $v === '') {
                unset($q[$k]);
            }
        }
        return http_build_query($q);
    }

    public static function pagination(Pager $p): string
    {
        if ($p->pages() <= 1) {
            return '';
        }
        $link = fn (int $n, string $label, bool $disabled = false, bool $active = false) =>
            '<li class="page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '') . '"><a class="page-link" href="' . e(base_path() . '/index.php?' . self::query(['page' => $n])) . '">' . $label . '</a></li>';
        $h = '<nav aria-label="Pages"><ul class="pagination pagination-sm mb-0 flex-wrap">';
        $h .= $link(max(1, $p->page - 1), '&laquo;', $p->page <= 1);
        $from = max(1, $p->page - 3);
        $to = min($p->pages(), $p->page + 3);
        if ($from > 1) {
            $h .= $link(1, '1') . ($from > 2 ? '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>' : '');
        }
        for ($i = $from; $i <= $to; $i++) {
            $h .= $link($i, (string) $i, false, $i === $p->page);
        }
        if ($to < $p->pages()) {
            $h .= ($to < $p->pages() - 1 ? '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>' : '') . $link($p->pages(), (string) $p->pages());
        }
        $h .= $link(min($p->pages(), $p->page + 1), '&raquo;', $p->page >= $p->pages());
        return $h . '</ul></nav>';
    }

    public static function sortLink(string $label, string $key, string $current, string $dir): string
    {
        $newDir = ($current === $key && $dir === 'asc') ? 'desc' : 'asc';
        $arrow = $current === $key ? ($dir === 'asc' ? ' <i class="fa-solid fa-arrow-up-short-wide small"></i>' : ' <i class="fa-solid fa-arrow-down-wide-short small"></i>') : '';
        return '<a class="text-reset text-decoration-none" href="' . e(base_path() . '/index.php?' . self::query(['sort' => $key, 'dir' => $newDir, 'page' => null])) . '">' . e($label) . $arrow . '</a>';
    }

    public static function empty(string $icon, string $title, string $text = ''): string
    {
        return '<div class="empty-state"><i class="fa-solid ' . e($icon) . '"></i><div class="fw-semibold">' . e($title) . '</div>' . ($text !== '' ? '<div class="text-muted small">' . e($text) . '</div>' : '') . '</div>';
    }

    /** A POST button/form that asks for confirmation (handled by app.js via data-confirm). */
    public static function postForm(string $action, array $hidden, string $label, string $class = 'btn btn-sm btn-outline-secondary', ?string $confirm = null): string
    {
        $h = '<form method="post" action="' . e($action) . '" class="d-inline"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field();
        foreach ($hidden as $k => $v) {
            $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
        }
        return $h . '<button class="' . e($class) . '">' . $label . '</button></form>';
    }
}
