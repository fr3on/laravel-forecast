<?php

namespace Fr3on\Forecast;

/**
 * Classifies a single SQL statement into an operation type, risk level,
 * human-readable reason, and optional remediation advice.
 *
 * Risk levels: SAFE | CAUTION | DANGER
 */
class SqlAnalyzer
{
    /**
     * @return array{operation: string, table: string, risk: string, reason: string, advice: string|null}
     */
    public function analyze(string $sql): array
    {
        $sql = trim($sql);

        return
            $this->matchDropTable($sql) ??
            $this->matchCreateTable($sql) ??
            $this->matchDropColumn($sql) ??
            $this->matchAddColumn($sql) ??
            $this->matchCreateIndex($sql) ??
            $this->matchRenameTable($sql) ??
            $this->matchRenameColumn($sql) ??
            $this->matchNotNull($sql) ??
            $this->matchAlterColumn($sql) ??
            $this->matchAlterTable($sql) ??
            $this->fallback($sql);
    }

    // ─── Pattern matchers (return null when the pattern does not match) ───────

    private function matchDropTable(string $sql): ?array
    {
        if (! preg_match('/^\s*DROP\s+TABLE/i', $sql)) {
            return null;
        }

        return $this->result(
            operation: 'DROP TABLE',
            table: $this->extractTable('/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?(?:\S+\.)?[`"\[]?(\w+)/i', $sql),
            risk: 'DANGER',
            reason: 'Entire table and all its data will be permanently deleted.',
            advice: null,
        );
    }

    private function matchCreateTable(string $sql): ?array
    {
        if (! preg_match('/^\s*CREATE\s+TABLE/i', $sql)) {
            return null;
        }

        return $this->result(
            operation: 'CREATE TABLE',
            table: $this->extractTable('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:\S+\.)?[`"\[]?(\w+)/i', $sql),
            risk: 'SAFE',
            reason: 'New table — no existing data affected.',
            advice: null,
        );
    }

    private function matchDropColumn(string $sql): ?array
    {
        if (! preg_match('/ALTER\s+TABLE/i', $sql) || ! preg_match('/\bDROP\s+COLUMN\b/i', $sql)) {
            return null;
        }

        return $this->result(
            operation: 'DROP COLUMN',
            table: $this->extractTable('/ALTER\s+TABLE\s+(?:\S+\.)?[`"\[]?(\w+)/i', $sql),
            risk: 'DANGER',
            reason: 'Column and all its data will be permanently removed.',
            advice: null,
        );
    }

    private function matchAddColumn(string $sql): ?array
    {
        if (! preg_match('/ALTER\s+TABLE/i', $sql) ||
            ! preg_match('/\bADD\s+(?:COLUMN\s+)?[`"\[]?\w+/i', $sql)) {
            return null;
        }

        $table = $this->extractTable('/ALTER\s+TABLE\s+(?:\S+\.)?[`"\[]?(\w+)/i', $sql);
        $hasDefault = (bool) preg_match('/\bDEFAULT\b/i', $sql);
        $nullable = preg_match('/\bNULL\b/i', $sql) && ! preg_match('/\bNOT\s+NULL\b/i', $sql);

        if (! $hasDefault && ! $nullable) {
            return $this->result(
                operation: 'ADD COLUMN',
                table: $table,
                risk: 'DANGER',
                reason: 'NOT NULL column with no DEFAULT — will fail on non-empty tables.',
                advice: 'Add a DEFAULT value or make the column nullable.',
            );
        }

        return $this->result(
            operation: 'ADD COLUMN',
            table: $table,
            risk: 'SAFE',
            reason: 'Column is nullable or has a DEFAULT — supports online DDL on MySQL 8+.',
            advice: null,
        );
    }

    private function matchCreateIndex(string $sql): ?array
    {
        if (! preg_match('/^\s*CREATE\s+(?:UNIQUE\s+)?INDEX/i', $sql)) {
            return null;
        }

        return $this->result(
            operation: 'CREATE INDEX',
            table: $this->extractTable('/\bON\s+(?:\S+\.)?[`"\[]?(\w+)/i', $sql),
            risk: 'CAUTION',
            reason: 'May lock the table during index creation on older engines.',
            advice: 'Consider ALGORITHM=INPLACE, LOCK=NONE for zero-downtime.',
        );
    }

    private function matchRenameTable(string $sql): ?array
    {
        $isRenameTable = preg_match('/^\s*RENAME\s+TABLE/i', $sql);
        $isAlterRename = preg_match('/ALTER\s+TABLE.*\bRENAME\s+TO\b/i', $sql);

        if (! $isRenameTable && ! $isAlterRename) {
            return null;
        }

        $table = $this->extractTable('/ALTER\s+TABLE\s+(?:\S+\.)?[`"\[]?(\w+)/i', $sql)
               ?: $this->extractTable('/RENAME\s+TABLE\s+(?:\S+\.)?[`"\[]?(\w+)/i', $sql);

        return $this->result(
            operation: 'RENAME TABLE',
            table: $table ?? '',
            risk: 'CAUTION',
            reason: 'May break existing queries, views, and foreign-key references.',
            advice: null,
        );
    }

    private function matchRenameColumn(string $sql): ?array
    {
        if (! preg_match('/ALTER\s+TABLE.*\bRENAME\s+COLUMN\b/i', $sql)) {
            return null;
        }

        return $this->result(
            operation: 'RENAME COLUMN',
            table: $this->extractTable('/ALTER\s+TABLE\s+(?:\S+\.)?[`"\[]?(\w+)/i', $sql),
            risk: 'CAUTION',
            reason: 'May break existing queries and application code.',
            advice: null,
        );
    }

    private function matchNotNull(string $sql): ?array
    {
        // Catches ALTER COLUMN … NOT NULL without ADD COLUMN (covered above)
        if (! preg_match('/ALTER\s+TABLE/i', $sql) ||
            ! preg_match('/\bNOT\s+NULL\b/i', $sql) ||
            ! preg_match('/\bMODIFY\b|\bCHANGE\b|\bALTER\s+COLUMN\b/i', $sql)) {
            return null;
        }

        return $this->result(
            operation: 'ADD NOT NULL',
            table: $this->extractTable('/ALTER\s+TABLE\s+(?:\S+\.)?[`"\[]?(\w+)/i', $sql),
            risk: 'DANGER',
            reason: 'Will fail if any existing row contains NULL in this column.',
            advice: 'Backfill NULL values before applying the constraint.',
        );
    }

    private function matchAlterColumn(string $sql): ?array
    {
        if (! preg_match('/ALTER\s+TABLE/i', $sql) ||
            ! preg_match('/\bMODIFY\b|\bCHANGE\b|\bALTER\s+COLUMN\b/i', $sql)) {
            return null;
        }

        return $this->result(
            operation: 'ALTER COLUMN',
            table: $this->extractTable('/ALTER\s+TABLE\s+(?:\S+\.)?[`"\[]?(\w+)/i', $sql),
            risk: 'CAUTION',
            reason: 'Type or constraint change — risk of data truncation.',
            advice: null,
        );
    }

    private function matchAlterTable(string $sql): ?array
    {
        if (! preg_match('/ALTER\s+TABLE/i', $sql)) {
            return null;
        }

        return $this->result(
            operation: 'ALTER TABLE',
            table: $this->extractTable('/ALTER\s+TABLE\s+(?:\S+\.)?[`"\[]?(\w+)/i', $sql),
            risk: 'CAUTION',
            reason: 'Table structure modification.',
            advice: null,
        );
    }

    private function fallback(string $sql): array
    {
        // Try to at least extract a table name from a generic SQL statement.
        $table = $this->extractTable('/\bFROM\s+[`"\[]?(\w+)/i', $sql)
               ?: $this->extractTable('/\bINTO\s+[`"\[]?(\w+)/i', $sql)
               ?: '';

        return $this->result(
            operation: 'UNKNOWN',
            table: $table,
            risk: 'CAUTION',
            reason: 'Unrecognised SQL statement — review manually.',
            advice: null,
        );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function extractTable(string $pattern, string $sql): string
    {
        return preg_match($pattern, $sql, $m) ? ($m[1] ?? '') : '';
    }

    /**
     * @return array{operation: string, table: string, risk: string, reason: string, advice: string|null}
     */
    private function result(
        string $operation,
        ?string $table,
        string $risk,
        string $reason,
        ?string $advice,
    ): array {
        return [
            'operation' => $operation,
            'table' => $table ?? '',
            'risk' => $risk,
            'reason' => $reason,
            'advice' => $advice,
        ];
    }
}
