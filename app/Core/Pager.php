<?php
namespace App\Core;

class Pager
{
    public int $page;
    public int $perPage;
    public int $total;

    public function __construct(int $total, int $perPage = 20, ?int $page = null)
    {
        $this->total = max(0, $total);
        $this->perPage = $perPage;
        $pages = max(1, (int) ceil($this->total / $perPage));
        $p = $page ?? (int) ($_GET['page'] ?? 1);
        $this->page = min(max(1, $p), $pages);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    /** SQL fragment; both numbers are integers produced by this class. */
    public function limitSql(): string
    {
        return ' LIMIT ' . (int) $this->perPage . ' OFFSET ' . (int) $this->offset();
    }
}
