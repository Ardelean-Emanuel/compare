orca_layout = `
    <div id="datenschutzbanner" class="container">
    <div role="dialog" class="d-flex  scontainer wrapper flexbox">
        <div class="ds-flex-left">
            <h3>Einwilligung</h3>
            <p>Auf dieser Website und im Portal ORCA.nrw nutzen wir den Webanalysedienst Matomo, um die Nutzung unserer Website zu analysieren und prüfen. Über die gewonnenen Statistiken können wir unser Angebot verbessern und für Sie interessanter ausgestalten.</p>
            <p>Sie können hier entscheiden, ob Sie neben technisch notwendigen Cookies erlauben, dass wir Ihre Daten zu diesen Zwecken verarbeiten und entsprechende Cookies setzen dürfen.</p>
            <p>Weitere Informationen zum Datenschutz - insbesondere zu „Cookies“ und „Matomo“ - finden Sie in unseren <a class="orca-textlink" href="https://www.orca.nrw/datenschutz" title="Zu unseren Datenschutzbestimmungen">Datenschutzhinweisen</a>. Sie können Ihre Einwilligung jederzeit widerrufen.</p>
        </div>
        <div class="ds-flex-right flexbox">
            <span class="orca-download ripple all-cookies" role="button">
                <a id="datenschutzbanner-button" onkeydown='{ if (event.keyCode === 13) { orcaModifyMtmCookie(0); }}' onclick="orcaModifyMtmCookie(0)" tabindex="0">Nur technisch notwendige Cookies</a>
            </span>
            <span class="orca-download ripple datenschutz-link" role="button">
                <a id="datenschutzbanner-button" href="https://www.orca.nrw/datenschutz" rel="noopener noreferrer nofollow" tabindex="0">Mehr Informationen</a>
            </span>
            <span class="orca-download ripple no-cookies" role="button">
                <a id="datenschutzbanner-button" onkeydown='{ if (event.keyCode === 13) { orcaModifyMtmCookie(1); }}' onclick="orcaModifyMtmCookie(1);" tabindex="0">AKZEPTIEREN</a>
            </span>
        </div>
    </div>
    </div>
    `;
//Prüfe ob nutzer schon einen Consent eingestellt hat
function orcaCheckMtmCookie() {
    var state = "";
    if (document.cookie.indexOf('mtm_consent_removed=') >= 0)
        return false;
    if (document.cookie.indexOf('mtm_consent=') >= 0)
        return false;
    return true;
}

function setCookie(cname, cvalue, exhour) {
    var d = new Date();
    d.setTime(d.getTime() + (exhour * 60 * 60 * 1000));
    var expires = "expires=" + d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

function orcaModifyMtmCookie(state) {
    //Banner ausblenen, muss wegen Matomo hierstehen
    var banner = document.getElementById("datenschutzbanner");
    banner.style.opacity = 0;
    setTimeout(function() {
        banner.style.display = "none";
    }, 500);
    var _paq = window._paq = window._paq || [];
    //Setze die entsprechenden Matomo-Cookies
    if (state === 1) {
        _paq.push(['rememberConsentGiven'], 24 * 30);
        setCookie('mtm_consent', new Date().getTime(), 24 * 30);
    } else {
        _paq.push(['forgetConsentGiven']);
        setCookie('mtm_consent_removed', new Date().getTime(), 24 * 30);
    }
}

//Einblenden des Banners
function orcaShowCookieBanner(force = 0) {
    if (force || orcaCheckMtmCookie()) {
        var banner = document.getElementById('datenschutzbanner');
        if (banner === null) {
            banner = document.createElement('div');
            banner.classList.add("container");
            banner.id = "datenschutzbanner";
            banner.innerHTML = orca_layout;
            banner.style.opacity = 0;
            document.body.appendChild(banner);
        } else {
            banner.style.display = "block";
        }
        document.removeEventListener('DOMContentLoaded', orcaShowCookieBanner)
        setTimeout(() => {
            banner.style.opacity = 1;
        }, 0.5);
    }
}

document.addEventListener('DOMContentLoaded', orcaShowCookieBanner(0));