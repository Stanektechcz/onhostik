"use strict"
var optionSettings = {
    layout:"wide",
    background:"white",
    color:"pink",
    header:"fixed",
    font:"opensans",
    textDirection:"ltr",
    radius:"sixradius",
    showSettings:"show",
};
new antlerSettings(optionSettings);

// Fallback: hide spinner if window.load already fired before scripts registered
(function() {
    function hideSpinner() {
        var s = document.getElementById('spinner-area');
        if (s) { s.style.opacity = '0'; setTimeout(function(){ s.style.display = 'none'; }, 400); }
    }
    if (document.readyState === 'complete') {
        hideSpinner();
    } else {
        window.addEventListener('load', hideSpinner);
    }
})();