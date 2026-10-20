<?php

namespace App\Services;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityRecorder
{
    private bool $enabled = false;
    private array $changes = [];

    public function start(): void
    {
        $this->changes = [];
        $this->enabled = true;
    }

    public function stop(): void
    {
        $this->enabled = false;
        $this->changes = [];
    }

    public function capture(QueryExecuted $event): void
    {
        if (!$this->enabled || !preg_match('/^\s*(insert(?:\s+ignore)?\s+into|update|delete\s+from)\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $event->sql, $match)) {
            return;
        }
        $table = strtolower($match[2]);
        if (in_array($table, ['activity_logs', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'])) {
            return;
        }
        $action = match (strtolower(strtok($match[1], ' '))) {
            'insert' => 'tambah',
            'update' => 'edit',
            'delete' => 'hapus',
        };
        // Store only operation metadata. SQL, bindings, and form secrets are never persisted.
        $reference = null;
        if ($action === 'tambah') {
            $id = $event->connection->getPdo()->lastInsertId();
            if (ctype_digit((string) $id) && (int) $id > 0) {
                $reference = 'id=' . $id;
            }
        } elseif (preg_match('/\bwhere\s+[`"]?id[`"]?\s*=\s*\?/i', $event->sql, $where, PREG_OFFSET_CAPTURE)) {
            $bindingIndex = substr_count(substr($event->sql, 0, $where[0][1]), '?');
            $id = $event->bindings[$bindingIndex] ?? null;
            if (ctype_digit((string) $id)) {
                $reference = 'id=' . $id;
            }
        }
        $this->changes[] = ['action' => $action, 'table' => $table, 'reference' => $reference, 'level' => $event->connection->transactionLevel()];
    }

    public function committed(int $level): void
    {
        foreach ($this->changes as &$change) {
            $change['level'] = min($change['level'], $level);
        }
    }

    public function rolledBack(int $level): void
    {
        $this->changes = array_values(array_filter($this->changes, fn ($change) => $change['level'] <= $level));
    }

    public function flush(Request $request, ?object $user): void
    {
        $this->enabled = false;
        if (!$user) {
            return;
        }
        $groups = [];
        foreach ($this->changes as $change) {
            if ($change['level'] !== 0) {
                continue;
            }
            $key = $change['action'] . ':' . $change['table'];
            $groups[$key] ??= ['action' => $change['action'], 'table' => $change['table'], 'count' => 0, 'references' => []];
            $groups[$key]['count']++;
            if ($change['reference']) {
                $groups[$key]['references'][] = $change['reference'];
            }
        }
        foreach ($groups as $group) {
            $this->write($request, $user, $group['action'], $group['table'], $group['count'], implode(', ', array_unique($group['references'])) ?: null);
        }
    }

    public function write(Request $request, object $user, string $action, ?string $table = null, int $operations = 1, ?string $reference = null): void
    {
        $parameters = $request->route()?->parameters() ?? [];
        $record = $reference ?? collect($parameters)->filter(fn ($value) => is_scalar($value))->map(fn ($value, $key) => $key . '=' . $value)->implode(', ');
        DB::table('activity_logs')->insert([
            'user_id' => $user->getAuthIdentifier(),
            'username' => $user->username ?? $user->surname ?? 'Pengguna',
            'action' => $action,
            'table_name' => $table,
            'record_reference' => $record ? mb_substr($record, 0, 255) : null,
            'route_name' => $request->route()?->getName(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr($request->userAgent() ?? '', 0, 500),
            'operations' => $operations,
            'created_at' => now('UTC'),
        ]);
    }
}
