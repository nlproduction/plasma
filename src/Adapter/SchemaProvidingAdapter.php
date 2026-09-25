<?php

namespace Plasma\Adapter;

/**
 * Optional adapter capability for schemas that are intrinsic to the runtime.
 *
 * @internal Applications normally register their own models through Plasma.
 */
interface SchemaProvidingAdapter
{
    /**
     * @return array<int, array<string, array<string, mixed>>>
     */
    public function defaultSchemas(): array;
}
