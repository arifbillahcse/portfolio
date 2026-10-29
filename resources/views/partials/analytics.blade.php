@if(app(\App\Support\AnalyticsTracking::class)->shouldRender($site, $seo ?? []))
<script>
(function () {
  var id = @json($site->ga4_measurement_id);
  if (navigator.doNotTrack === '1' || navigator.globalPrivacyControl === true) return;
  window.dataLayer = window.dataLayer || [];
  window.gtag = function () { window.dataLayer.push(arguments); };
  gtag('js', new Date());
  gtag('config', id, { anonymize_ip: true });
  var tag = document.createElement('script');
  tag.async = true;
  tag.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
  document.head.appendChild(tag);
  function lead(source) { gtag('event', 'generate_lead', { form_source: source }); }
  var send = window.fetch;
  if (send) {
    window.fetch = function (input) {
      var request = send.apply(this, arguments);
      try {
        var url = typeof input === 'string' ? input : (input && input.url) || '';
        if (/\/inquiries(?:\?|$)/.test(url)) {
          request.then(function (response) { if (response.status === 201) lead('ajax'); }).catch(function () {});
        }
      } catch (error) {}
      return request;
    };
  }
  @if(session()->has('inquiry_success'))
  lead('form');
  @endif
})();
</script>
@endif
