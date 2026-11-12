<?php
use App\Core\{Dates, Db, Http, Pager, View};

$from = Http::strParam('from');
$to = Http::strParam('to');
$f = ['q' => trim(Http::strParam('q')), 'user' => trim(Http::strParam('user')), 'action' => trim(Http::strParam('action')),
      'from' => Dates::isValid($from) ? $from : '', 'to' => Dates::isValid($to) ? $to : ''];
$where = ['1=1'];
$p = [];
if ($f['user'] !== '') { $where[] = 'username LIKE ?'; $p[] = '%' . $f['user'] . '%'; }
if ($f['action'] !== '') { $where[] = 'action LIKE ?'; $p[] = $f['action'] . '%'; }
if ($f['q'] !== '') { $where[] = '(details LIKE ? OR entity_type LIKE ? OR entity_id = ?)'; $p[] = '%' . $f['q'] . '%'; $p[] = '%' . $f['q'] . '%'; $p[] = $f['q']; }
if ($f['from'] !== '') { $where[] = 'created_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
if ($f['to'] !== '') { $where[] = 'created_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
$w = implode(' AND ', $where);
$pager = new Pager((int) Db::val("SELECT COUNT(*) FROM audit_logs WHERE $w", $p), 50);
$rows = Db::all("SELECT * FROM audit_logs WHERE $w ORDER BY id DESC" . $pager->limitSql(), $p);
View::page('audit/index', ['rows' => $rows, 'pager' => $pager, 'f' => $f], 'Audit log');
