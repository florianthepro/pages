<?php
declare(strict_types=1);
# Geteilte Variablen aus der Instanz defensiv einlesen (Muster wie launcher-v2).
$htmlcreator_theme=(isset($htmlcreator_theme)&&in_array($htmlcreator_theme,['light','dark'],true))?$htmlcreator_theme:'auto';
$htmlcreator_title=(isset($htmlcreator_title)&&$htmlcreator_title!=='')?(string)$htmlcreator_title:'HTML Creator';
$htmlcreator_icon=(isset($htmlcreator_icon)&&is_string($htmlcreator_icon)&&$htmlcreator_icon!=='')?$htmlcreator_icon:'https://raw.githubusercontent.com/florianthepro/pages/main/content/media/html-creator/index.svg';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
# data-theme nur bei fix light/dark setzen, sonst (auto) weglassen.
$dt=$htmlcreator_theme==='light'?' data-theme="light"':($htmlcreator_theme==='dark'?' data-theme="dark"':'');
# Rein clientseitiges Tool: HTML-Dokumente werden komplett im Browser erzeugt,
# es gibt keine Serverseite. JSZip kommt vom oeffentlichen CDN.
?>
<!doctype html>
<html lang="de"<?= $dt ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($htmlcreator_title) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= h($htmlcreator_icon) ?>">
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
<style>
/* Original-CSS des Tools, auf CSS-Variablen umgestellt fuer Hell/Dunkel/Auto. */
:root {
    --bg: #f5f5f5;
    --fg: #222;
    --accent: #005a9e;
    --border: #ccc;
    --danger: #b00020;
    --panel: #fff;
    --muted: #666;
    --badge-fg: #555;
    --field-bg: #fff;
    --btn-bg: #e6e6e6;
    --out-bg: #fafafa;
    --font-mono: Consolas, "Fira Code", Menlo, Monaco, "Courier New", monospace;
}
@media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
        --bg: #16181c;
        --fg: #e6e7ea;
        --accent: #4c9dff;
        --border: #363a42;
        --danger: #ff6b6b;
        --panel: #1e2126;
        --muted: #9aa0aa;
        --badge-fg: #b6bcc6;
        --field-bg: #14161a;
        --btn-bg: #2a2e35;
        --out-bg: #121418;
    }
}
:root[data-theme="dark"] {
    --bg: #16181c;
    --fg: #e6e7ea;
    --accent: #4c9dff;
    --border: #363a42;
    --danger: #ff6b6b;
    --panel: #1e2126;
    --muted: #9aa0aa;
    --badge-fg: #b6bcc6;
    --field-bg: #14161a;
    --btn-bg: #2a2e35;
    --out-bg: #121418;
}
* {
    box-sizing: border-box;
}
body {
    margin: 0;
    padding: 0;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background: var(--bg);
    color: var(--fg);
}
header {
    padding: 1rem 1.5rem;
    background: var(--panel);
    border-bottom: 1px solid var(--border);
}
header h1 {
    margin: 0;
    font-size: 1.4rem;
}
main {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
    gap: 1rem;
    padding: 1rem 1.5rem 1.5rem;
}
@media (max-width: 960px) {
    main {
        grid-template-columns: 1fr;
    }
}
section {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 1rem 1.25rem 1.25rem;
}
section h2 {
    margin-top: 0;
    font-size: 1.1rem;
    border-bottom: 1px solid var(--border);
    padding-bottom: 0.5rem;
}
fieldset {
    border: 1px solid var(--border);
    border-radius: 4px;
    margin-bottom: 0.75rem;
    padding: 0.75rem 0.75rem 0.9rem;
}
fieldset legend {
    padding: 0.1rem 0.3rem;
    font-weight: 600;
    font-size: 0.95rem;
}
label {
    display: block;
    font-size: 0.9rem;
    margin: 0.25rem 0 0.15rem;
}
input[type="text"],
input[type="url"],
input[type="file"],
textarea,
select {
    width: 100%;
    padding: 0.35rem 0.4rem;
    font-size: 0.9rem;
    border-radius: 3px;
    border: 1px solid var(--border);
    font-family: inherit;
    background: var(--field-bg);
    color: var(--fg);
}
textarea {
    resize: vertical;
    min-height: 4rem;
}
.small-input {
    max-width: 200px;
}
.inline-options {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 0.25rem;
    font-size: 0.9rem;
}
.inline-options label {
    display: inline-flex;
    align-items: center;
    margin: 0;
}
.inline-options input {
    margin-right: 0.35rem;
    width: auto;
}
.hint {
    font-size: 0.8rem;
    color: var(--muted);
    margin-top: 0.1rem;
}
.error {
    color: var(--danger);
    font-size: 0.8rem;
    margin-top: 0.15rem;
}
.button-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.75rem;
}
button {
    padding: 0.45rem 0.8rem;
    font-size: 0.9rem;
    border-radius: 3px;
    border: 1px solid var(--border);
    cursor: pointer;
    background: var(--btn-bg);
    color: var(--fg);
}
button.primary {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
}
button:disabled {
    opacity: 0.4;
    cursor: default;
}
.outputs {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.output-block label {
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.output-block textarea {
    margin-top: 0.25rem;
    font-family: var(--font-mono);
    font-size: 0.85rem;
    min-height: 8rem;
    background: var(--out-bg);
}
.badge {
    display: inline-block;
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    border: 1px solid var(--border);
    font-size: 0.75rem;
    color: var(--badge-fg);
}
#btnGenerate {
    display: none;
}
</style>
</head>
<body>
<header>
    <h1>HTML Dokument Generator</h1>
</header>
<main>
<section aria-label="Konfiguration">
<h2>Konfiguration</h2>
<form id="configForm">
<fieldset>
<legend>Dokument-Metadaten</legend>
<label for="lang">Sprache (de, en)</label>
<input id="lang" name="lang" type="text" class="small-input" placeholder="de">
<div class="hint"><code>lang</code>-Attribut in &lt;html&gt;-Tag</div>
<label for="title">Seitentitel *</label>
<input id="title" name="title" type="text" required="">
<label for="headline">Hauptüberschrift (H1)</label>
<input id="headline" name="headline" type="text" placeholder="Standard: Seitentitel">
<label for="description">Meta-Description</label>
<textarea id="description" name="description" rows="2" placeholder="Kurzbeschreibung der Seite (optional)"></textarea>
</fieldset>

<fieldset>
<legend>Stylesheet</legend>
<label>Einbettung</label>
<div class="inline-options">
<label><input type="radio" name="cssMode" value="inline" checked="">Inline</label>
<label><input type="radio" name="cssMode" value="external">Extern</label>
<label><input type="radio" name="cssMode" value="none">Kein CSS</label>
</div>
<label for="cssCode">CSS-Code</label>
<textarea id="cssCode" name="cssCode" rows="6" placeholder="/* CSS-Code */"></textarea>
</fieldset>

<fieldset>
<legend>JavaScript</legend>
<label>Einbettung</label>
<div class="inline-options">
<label><input type="radio" name="jsMode" value="inline" checked="">Inline</label>
<label><input type="radio" name="jsMode" value="external">Extern</label>
<label><input type="radio" name="jsMode" value="none">Kein JavaScript</label>
</div>
<label for="jsCode">JavaScript-Code</label>
<textarea id="jsCode" name="jsCode" rows="6" placeholder="// Optionaler JavaScript-Code"></textarea></fieldset>

<fieldset>
<legend>Bild</legend>
<label for="imageFile">Lokal</label>
<input id="imageFile" name="imageFile" type="file" accept="image/*">
<label for="imageUrl">Extern</label>
<input id="imageUrl" name="imageUrl" type="url" placeholder="https://example.com/bild.png">
<label>Verwendung</label>
<div class="inline-options">
<label><input type="radio" name="imageSource" value="file" checked="">Lokale</label>
<label><input type="radio" name="imageSource" value="url">Extern</label>
</div>
<label>Bild-Verwendung</label>
<div class="inline-options">
<label><input type="checkbox" id="useAsFavicon">Als Favicon verwenden</label>
<label><input type="checkbox" id="embedInBody">Im Body einbetten (&lt;img&gt;)</label>
</div>
<div class="inline-options" style="margin-top:0.4rem;">
<label><input type="checkbox" id="includeOriginalImage">Originalbild als eigene Datei bereitstellen / ins ZIP aufnehmen</label>
</div>
</fieldset>

<div id="validationError" class="error" style="display:none;"></div>
</form>
</section>

<section aria-label="Ausgabe">
<h2>Ausgabe &amp; Downloads</h2>

<div class="outputs">

<div class="output-block">
<label for="htmlOutput">HTML-Dokument
<span class="badge">index.html</span>
</label>
<textarea id="htmlOutput" readonly=""></textarea>
</div>

<div class="output-block">
<label for="cssOutput">CSS
<span class="badge">style.css</span>
</label>
<textarea id="cssOutput" readonly=""></textarea>
</div>

<div class="output-block">
<label for="jsOutput">JavaScript<span class="badge">script.js</span>
</label>
<textarea id="jsOutput" readonly=""></textarea>
</div>
</div>

<div class="button-row" style="margin-top:1rem;">
<button type="button" id="btnDownloadHtml" disabled="">HTML herunterladen</button>
<button type="button" id="btnDownloadCss" disabled="">CSS herunterladen</button>
<button type="button" id="btnDownloadJs" disabled="">JS herunterladen</button>
<button type="button" id="btnDownloadImage" disabled="">Bild herunterladen</button>
<button type="button" id="btnDownloadZip" disabled="">Alles als ZIP herunterladen</button>
</div>
</section>
</main>
<script>
/* Original-Tool-Logik (html-creator-v4.js), unveraendert inline eingebettet. */
(function () {
    "use strict";

    const form = document.getElementById("configForm");
    const validationErrorEl = document.getElementById("validationError");

    const htmlOutputEl = document.getElementById("htmlOutput");
    const cssOutputEl = document.getElementById("cssOutput");
    const jsOutputEl = document.getElementById("jsOutput");

    const btnDownloadHtml = document.getElementById("btnDownloadHtml");
    const btnDownloadCss = document.getElementById("btnDownloadCss");
    const btnDownloadJs = document.getElementById("btnDownloadJs");
    const btnDownloadImage = document.getElementById("btnDownloadImage");
    const btnDownloadZip = document.getElementById("btnDownloadZip");

    const imageFileInput = document.getElementById("imageFile");
    const imageUrlInput = document.getElementById("imageUrl");
    const useAsFaviconCheckbox = document.getElementById("useAsFavicon");
    const embedInBodyCheckbox = document.getElementById("embedInBody");
    const includeOriginalImageCheckbox = document.getElementById("includeOriginalImage");

    // Zustand
    const state = {
        html: "",
        css: "",
        js: "",
        imageFile: null,             // Original lokales Bild (optional für Download/ZIP)
        imageDataUrlFromFile: "",    // Data-URL aus lokalem File
        imageDataUrlFromUrl: "",     // Data-URL aus externer URL
        lastExternalImageUrl: ""     // für welche URL wurde imageDataUrlFromUrl generiert?
    };

    function escapeHtml(text) {
        if (!text) return "";
        return text.replace(/[&<>"']/g, function (ch) {
            switch (ch) {
                case "&": return "&amp;";
                case "<": return "&lt;";
                case ">": return "&gt;";
                case '"': return "&quot;";
                case "'": return "&#39;";
                default: return ch;
            }
        });
    }

    function escapeHtmlAttr(text) {
        return escapeHtml(text);
    }

    function updateDownloadButtons() {
        btnDownloadHtml.disabled = !state.html;
        btnDownloadCss.disabled = !state.css;
        btnDownloadJs.disabled = !state.js;
        btnDownloadImage.disabled = !state.imageFile;
        const hasAny =
            !!state.html ||
            !!state.css ||
            !!state.js ||
            !!state.imageFile;
        btnDownloadZip.disabled = !hasAny;
    }

    function downloadBlob(content, mimeType, fileName) {
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    function downloadFileObject(file) {
        const url = URL.createObjectURL(file);
        const a = document.createElement("a");
        a.href = url;
        a.download = file.name || "image";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // Lokales Bild → Data-URL
    imageFileInput.addEventListener("change", function (event) {
        const file = event.target.files && event.target.files[0];
        state.imageFile = null;
        state.imageDataUrlFromFile = "";

        if (!file) {
            updateDownloadButtons();
            autoGenerate();
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            state.imageDataUrlFromFile = e.target.result;
            state.imageFile = file;
            updateDownloadButtons();
            autoGenerate(); // neu generieren, sobald Data-URL vorhanden
        };
        reader.readAsDataURL(file);
    });

    // Externe URL → Data-URL (CORS-abhängig)
    async function convertExternalImageToDataUrl(url) {
        if (!url) {
            state.imageDataUrlFromUrl = "";
            state.lastExternalImageUrl = "";
            autoGenerate();
            return;
        }

        // Wenn bereits für diese URL konvertiert, nichts tun
        if (state.lastExternalImageUrl === url && state.imageDataUrlFromUrl) {
            autoGenerate();
            return;
        }

        try {
            const response = await fetch(url, { mode: "cors" });
            if (!response.ok) {
                console.warn("Bild-URL konnte nicht geladen werden:", response.status);
                state.imageDataUrlFromUrl = "";
                state.lastExternalImageUrl = "";
                autoGenerate();
                return;
            }

            const blob = await response.blob();
            const reader = new FileReader();
            reader.onload = function (e) {
                state.imageDataUrlFromUrl = e.target.result;
                state.lastExternalImageUrl = url;
                autoGenerate();
            };
            reader.readAsDataURL(blob);
        } catch (err) {
            console.warn("Fehler beim Laden externer Bild-URL:", err);
            state.imageDataUrlFromUrl = "";
            state.lastExternalImageUrl = "";
            autoGenerate();
        }
    }

    function validateForm() {
        validationErrorEl.style.display = "none";
        validationErrorEl.textContent = "";

        const title = form.title.value.trim();

        if (!title) {
            validationErrorEl.textContent = "Seitentitel ist erforderlich.";
            validationErrorEl.style.display = "block";
            return false;
        }

        return true;
    }

    function buildImageTag(context, imageSourceMode, externalImageUrl) {
        // context: "favicon" oder "body"

        const hasFileData = !!state.imageDataUrlFromFile;
        const hasUrlData = !!state.imageDataUrlFromUrl;
        const hasExternalUrl = !!externalImageUrl;

        let src = "";

        if (imageSourceMode === "file") {
            // primär lokales Bild (falls Data-URL vorhanden)
            if (hasFileData) {
                src = state.imageDataUrlFromFile;
            } else if (hasUrlData) {
                // fallback: externe URL als Data-URL (falls schon konvertiert)
                src = state.imageDataUrlFromUrl;
            } else if (hasExternalUrl) {
                // fallback: direkte URL, falls noch keine Data-URL verfügbar
                src = externalImageUrl;
            }
        } else if (imageSourceMode === "url") {
            // primär Data-URL aus externer URL
            if (hasUrlData) {
                src = state.imageDataUrlFromUrl;
            } else if (hasExternalUrl) {
                // solange noch nicht konvertiert, notfalls direkte URL verwenden
                src = externalImageUrl;
            } else if (hasFileData) {
                // fallback: lokales Bild
                src = state.imageDataUrlFromFile;
            }
        }

        if (!src) {
            return "";
        }

        if (context === "favicon") {
            return '<link rel="icon" href="' + escapeHtmlAttr(src) + '">';
        }

        return '<img src="' + escapeHtmlAttr(src) + '" alt="">';
    }

    function generateDocument() {
        if (!validateForm()) {
            state.html = "";
            htmlOutputEl.value = "";
            cssOutputEl.value = "";
            jsOutputEl.value = "";
            updateDownloadButtons();
            return;
        }

        state.html = "";
        state.css = "";
        state.js = "";
        // Originalbild nur behalten, wenn gewählt
        state.imageFile = includeOriginalImageCheckbox.checked ? state.imageFile : null;

        const lang = (form.lang.value || "de").trim() || "de";
        const rawTitle = form.title.value.trim();
        const rawHeadline = form.headline.value.trim();
        const rawDescription = form.description.value.trim();

        const cssMode = (form.cssMode.value || "inline");
        const cssCode = form.cssCode.value || "";

        const jsMode = (form.jsMode.value || "inline");
        const jsCode = form.jsCode.value || "";

        const imageSourceMode = form.imageSource.value || "file";
        const externalImageUrl = imageUrlInput.value.trim();

        const useAsFavicon = useAsFaviconCheckbox.checked;
        const embedInBody = embedInBodyCheckbox.checked;
        const includeOriginalImage = includeOriginalImageCheckbox.checked;

        const title = rawTitle || "Ohne Titel";
        const headline = rawHeadline || title;

        let headParts = [];
        let bodyParts = [];

        // KEINE Entities, echte Tags
        headParts.push('<meta charset="utf-8">');

        if (rawDescription) {
            headParts.push(
                '<meta name="description" content="' +
                escapeHtmlAttr(rawDescription) + '">'
            );
        }

        if (useAsFavicon) {
            const favTag = buildImageTag("favicon", imageSourceMode, externalImageUrl);
            if (favTag) {
                headParts.push(favTag);
            }
        }

        const trimmedCss = cssCode.trim();
        if (cssMode === "inline" && trimmedCss) {
            headParts.push("<style>");
            headParts.push(trimmedCss);
            headParts.push("</style>");
        } else if (cssMode === "external" && trimmedCss) {
            // korrektes externes CSS
            headParts.push('<link rel="stylesheet" href="style.css">');
            state.css = trimmedCss;
        }

        const trimmedJs = jsCode.trim();
        if (jsMode === "inline" && trimmedJs) {
            headParts.push("<script>");
            const safeJs = trimmedJs.replace(/<\/script>/gi, "<\\/script>");
            headParts.push(safeJs);
            headParts.push("<\/script>");
        } else if (jsMode === "external" && trimmedJs) {
            // korrektes externes JS
            headParts.push('<script src="script.js"><\/script>');
            state.js = trimmedJs;
        }

        // Body
        bodyParts.push("<h1>" + escapeHtml(headline) + "</h1>");

        if (embedInBody) {
            const imgTag = buildImageTag("body", imageSourceMode, externalImageUrl);
            if (imgTag) {
                bodyParts.push(imgTag);
            }
        }

        // KEINE Einrückungen (keine Leerzeichen vor den Zeilen)
        const htmlDoc =
            "<!DOCTYPE html>\n" +
            '<html lang="' + escapeHtmlAttr(lang || "de") + '">\n' +
            "<head>\n" +
            "<title>" + escapeHtml(title) + "</title>\n" +
            (headParts.length ? headParts.join("\n") + "\n" : "") +
            "</head>\n" +
            "<body>\n" +
            (bodyParts.length ? bodyParts.join("\n") + "\n" : "") +
            "</body>\n" +
            "</html>\n";

        state.html = htmlDoc;

        if (!includeOriginalImage) {
            state.imageFile = null;
        }

        htmlOutputEl.value = htmlDoc;
        cssOutputEl.value = state.css || "";
        jsOutputEl.value = state.js || "";

        updateDownloadButtons();
    }

    function autoGenerate() {
        try {
            generateDocument();
        } catch (err) {
            console.error(err);
            validationErrorEl.textContent = "Unerwarteter Fehler bei der Dokumenterzeugung.";
            validationErrorEl.style.display = "block";
        }
    }

    // Formular-Änderungen triggern automatische Generierung
    form.addEventListener("input", function (e) {
        if (e.target === imageFileInput) return;

        if (e.target === imageUrlInput) {
            // externe URL → in Data-URL konvertieren
            const url = imageUrlInput.value.trim();
            convertExternalImageToDataUrl(url);
            return;
        }

        autoGenerate();
    });

    form.addEventListener("change", function (e) {
        if (e.target === imageFileInput) return;

        if (e.target === imageUrlInput) {
            const url = imageUrlInput.value.trim();
            convertExternalImageToDataUrl(url);
            return;
        }

        autoGenerate();
    });

    // Download-Buttons
    btnDownloadHtml.addEventListener("click", function () {
        if (!state.html) return;
        downloadBlob(state.html, "text/html;charset=utf-8", "index.html");
    });

    btnDownloadCss.addEventListener("click", function () {
        if (!state.css) return;
        downloadBlob(state.css, "text/css;charset=utf-8", "style.css");
    });

    btnDownloadJs.addEventListener("click", function () {
        if (!state.js) return;
        downloadBlob(state.js, "text/javascript;charset=utf-8", "script.js");
    });

    btnDownloadImage.addEventListener("click", function () {
        if (!state.imageFile) return;
        downloadFileObject(state.imageFile);
    });

    btnDownloadZip.addEventListener("click", function () {
        const hasAny =
            !!state.html ||
            !!state.css ||
            !!state.js ||
            !!state.imageFile;

        if (!hasAny) return;

        if (typeof JSZip === "undefined") {
            alert("JSZip konnte nicht geladen werden. ZIP-Erzeugung nicht möglich.");
            return;
        }

        const zip = new JSZip();

        if (state.html) {
            zip.file("index.html", state.html);
        }
        if (state.css) {
            zip.file("style.css", state.css);
        }
        if (state.js) {
            zip.file("script.js", state.js);
        }
        if (state.imageFile) {
            zip.file(state.imageFile.name || "image", state.imageFile);
        }

        zip.generateAsync({ type: "blob" }).then(function (blob) {
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = "website.zip";
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }).catch(function (err) {
            console.error(err);
            alert("Fehler bei der ZIP-Erzeugung.");
        });
    });

    // Initial
    updateDownloadButtons();
})();
</script>
</body>
</html>
