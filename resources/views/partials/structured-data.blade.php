@php
    /**
     * Machine-readable identity for crawlers and agents that will never speak
     * MCP — the widest-reach discovery surface the site has.
     *
     * Built from the same $resumeData the page renders, so the two cannot
     * describe the owner differently.
     *
     * Deliberately carries no email address and no telephone. schema.org/Person
     * supports both, and the MCP endpoint withholds them from anonymous
     * callers; publishing them here, on a page anyone can fetch, would make
     * that decision meaningless.
     */
    $personal = $resumeData['personal'] ?? [];

    $sameAs = collect([
        ! empty($personal['linkedin'])
            ? (str_starts_with($personal['linkedin'], 'http') ? $personal['linkedin'] : 'https://'.$personal['linkedin'])
            : null,
    ])->filter()->values()->all();

    $structuredData = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'ProfilePage',
        'mainEntity' => array_filter([
            '@type' => 'Person',
            'name' => $personal['name'] ?? null,
            'jobTitle' => $personal['title'] ?? null,
            'description' => $personal['summary'] ?? null,
            'url' => $personal['url'] ?? config('app.url'),
            'sameAs' => $sameAs ?: null,
            // Points an agent at the page that explains the MCP endpoint, so
            // it can hand a person somewhere readable rather than a protocol
            // URL that answers 405 in a browser.
            'subjectOf' => [
                '@type' => 'WebPage',
                'name' => 'AI work and MCP endpoint',
                'url' => url('/ai'),
            ],
        ], static fn ($value) => $value !== null && $value !== ''),
    ], static fn ($value) => $value !== null && $value !== '');
@endphp

<script type="application/ld+json">
{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_PRETTY_PRINT) !!}
</script>
