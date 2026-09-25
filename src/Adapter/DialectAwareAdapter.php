<?php

namespace Plasma\Adapter;

/** Optional adapter capability for SQL dialect-specific operations. */
interface DialectAwareAdapter
{
    /** Return a normalized driver/dialect name such as mysql or sqlite. */
    public function getDialect(): string;
}
