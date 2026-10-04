/* /dokumentace/api: the error filter and the Redoc start. A separate file so the page needs no inline script (its CSP allows none). */
(function () {
  'use strict';

  var input = document.getElementById('err-filter');
  var rows = document.querySelectorAll('#err-table tbody tr');
  if (input) {
    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase();
      for (var i = 0; i < rows.length; i++) {
        rows[i].style.display = !q || rows[i].textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
      }
    });
  }

  var host = document.getElementById('redoc');
  // Redoc's footer asks its vendor's CDN for a logo; the page policy refuses it (nothing is fetched, the logo hides itself)
  if (host && window.Redoc) {
    // an error link (#some-slug) must survive Redoc's own hash handling: remember it and scroll once Redoc has rendered
    var target = location.hash;
    window.Redoc.init(host.getAttribute('data-spec-url'), {
      hideDownloadButton: false,
      expandResponses: '200,201',
      theme: {
        typography: { fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', headings: { fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif' } },
        colors: { primary: { main: '#ae1800' } }
      }
    }, host, function () {
      var el = target ? document.getElementById(decodeURIComponent(target.slice(1))) : null;
      if (el) el.scrollIntoView();
    });
  }
})();
