(function () {
  try {
    feather.replace();
  } catch (e) {
    document.querySelectorAll('[data-feather]').forEach(function (el) {
      try {
        var name = el.getAttribute('data-feather');
        if (feather.icons[name]) {
          var attrs = {};
          Array.prototype.slice.call(el.attributes).forEach(function(a) {
            if (a.name !== 'data-feather') attrs[a.name] = a.value;
          });
          var svg = feather.icons[name].toSvg(attrs);
          el.outerHTML = svg;
        }
      } catch (err) {}
    });
  }
})();
