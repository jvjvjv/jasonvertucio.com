{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>Jason Vertucio</title>
    <link>{{ url('/blog') }}</link>
    <atom:link href="{{ route('feed') }}" rel="self" type="application/rss+xml" />
    <description>Jason Vertucio does mobile application development.</description>
    <language>en-us</language>
    <lastBuildDate>{{ ($posts->first()?->published_at ?? now())->toRfc2822String() }}</lastBuildDate>
    @foreach ($posts as $post)
    <item>
      <title>{{ $post->title }}</title>
      <link>{{ route('post', ['slug' => $post->slug]) }}</link>
      <guid isPermaLink="true">{{ route('post', ['slug' => $post->slug]) }}</guid>
      <pubDate>{{ $post->published_at->toRfc2822String() }}</pubDate>
      @if ($post->user)
      <author>{{ $post->user->email }} ({{ $post->user->name }})</author>
      @endif
      <description><![CDATA[{{ $post->summary ?: Str::limit(strip_tags($post->body), 300) }}]]></description>
    </item>
    @endforeach
  </channel>
</rss>
