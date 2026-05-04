<?php

/**
 * Centralised SQL escaping helpers.
 *
 * BGA's Table class provides `escapeStringForDB()` at runtime. We wrap it so the
 * modules can be unit-tested in isolation (the trait is mixed into Hacknslash,
 * which extends Table).
 */
trait HNS_DbHelpers
{
    /**
     * Escape a string for safe interpolation inside a single-quoted SQL literal.
     */
    protected function hns_sql_escape(string $value): string
    {
        if (method_exists($this, 'escapeStringForDB')) {
            return $this->escapeStringForDB($value);
        }

        // Fallback for unit tests: escape the characters that matter for
        // single-quoted SQL literals.  Unlike addslashes() this does not
        // mangle multibyte sequences because it only operates on single-byte
        // ASCII control characters.
        return str_replace(
            ["\\", "'", "\0", "\n", "\r", "\x1a"],
            ["\\\\", "\\'", "\\0", "\\n", "\\r", "\\Z"],
            $value
        );
    }

    /**
     * Render a string as a single-quoted SQL literal, or `NULL` when the value
     * is null. Use for nullable columns.
     */
    protected function hns_sql_nullable_string(?string $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return "'" . $this->hns_sql_escape($value) . "'";
    }
}
