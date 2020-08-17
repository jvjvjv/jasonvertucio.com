<!-- Global site tag (gtag.js) - Google Analytics -->
@if (env('APP_DEBUG'))
<script cookie-consent="tracking">
console.log("%cYou're in debug mode. No Google tracking will be performed.","font:bold 20pt Impact;color:#842111;background-color:#ff0;padding:0.25rem;")
</script>
@else
<script cookie-consent="tracking" async src="https://www.googletagmanager.com/gtag/js?id=UA-11841699-3"></script>
<script cookie-consent="tracking">
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'UA-11841699-3');
</script>
@endif
