<?php
// Rich Text Editor Toolbar Component
// This can be included in any page that needs a WYSIWYG editor
?>

<style>
.editor-container {
    width: 100%;
    margin: 20px 0;
}

.editor-toolbar {
    background: linear-gradient(135deg, #111 0%, #1a1a1a 100%);
    border: 1px solid #333;
    border-bottom: none;
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 4px;
    border-radius: 6px 6px 0 0;
    flex-wrap: wrap;
}

.toolbar-group {
    display: flex;
    gap: 2px;
    align-items: center;
    padding: 0 6px;
    border-right: 1px solid #333;
}

.toolbar-group:last-child {
    border-right: none;
}

.btn {
    background: #222;
    color: #ddd;
    border: 1px solid #333;
    padding: 6px 10px;
    cursor: pointer;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.2s ease;
    min-width: 32px;
}

.btn:hover {
    background: #333;
    border-color: #555;
    color: #fff;
}

.btn:active {
    background: #1a1a1a;
    border-color: #666;
}

.select, .input-color {
    background: #222;
    color: #ddd;
    border: 1px solid #333;
    padding: 6px 8px;
    border-radius: 4px;
    font-size: 13px;
    cursor: pointer;
}

.select:hover {
    background: #333;
    border-color: #555;
}

.input-color {
    width: 40px;
    padding: 4px;
    cursor: pointer;
}

.editor {
    width: 100%;
    min-height: 300px;
    background: #000;
    color: #fff;
    border: 1px solid #333;
    padding: 15px;
    border-radius: 0 0 6px 6px;
    font-size: 14px;
    font-family: 'Courier New', monospace;
    resize: vertical;
    line-height: 1.6;
}

.editor:focus {
    outline: none;
    border-color: #555;
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1);
}

.icon {
    font-style: normal;
    display: inline-block;
}
</style>

<div class="editor-container">
    <div class="editor-toolbar">
        <!-- Undo/Redo -->
        <div class="toolbar-group">
            <button class="btn" title="Undo" onclick="execUndoRedo('undo')">↶</button>
            <button class="btn" title="Redo" onclick="execUndoRedo('redo')">↷</button>
        </div>

        <!-- Font Size -->
        <div class="toolbar-group">
            <select class="select" title="Font Size" onchange="execFontSize(this.value)">
                <option value="">Size</option>
                <option value="12">12px</option>
                <option value="14">14px</option>
                <option value="16">16px</option>
                <option value="18">18px</option>
                <option value="20">20px</option>
                <option value="24">24px</option>
                <option value="28">28px</option>
                <option value="32">32px</option>
            </select>
        </div>

        <!-- Font Family -->
        <div class="toolbar-group">
            <select class="select" title="Font Family" onchange="execFontFamily(this.value)">
                <option value="">Font</option>
                <option value="'Open Sans'">Open Sans</option>
                <option value="'Roboto'">Roboto</option>
                <option value="'Poppins'">Poppins</option>
                <option value="'Arial'">Arial</option>
                <option value="Georgia">Georgia</option>
                <option value="'Courier New'">Courier New</option>
            </select>
        </div>

        <!-- Text Formatting -->
        <div class="toolbar-group">
            <button class="btn" title="Bold (Ctrl+B)" onclick="execCommand('bold')"><b>B</b></button>
            <button class="btn" title="Italic (Ctrl+I)" onclick="execCommand('italic')"><i>I</i></button>
            <button class="btn" title="Underline (Ctrl+U)" onclick="execCommand('underline')"><u>U</u></button>
            <button class="btn" title="Strikethrough" onclick="execCommand('strikeThrough')"><s>S</s></button>
        </div>

        <!-- Text Effects -->
        <div class="toolbar-group">
            <button class="btn" title="Superscript" onclick="execCommand('superscript')">X<sup>2</sup></button>
            <button class="btn" title="Subscript" onclick="execCommand('subscript')">X<sub>2</sub></button>
        </div>

        <!-- Colors -->
        <div class="toolbar-group">
            <label class="btn" title="Text Color" style="padding: 0; overflow: hidden;">
                <input type="color" class="input-color" onchange="execForeColor(this.value)" title="Text Color">
            </label>
            <label class="btn" title="Highlight Color" style="padding: 0; overflow: hidden;">
                <input type="color" class="input-color" onchange="execBackColor(this.value)" value="#ffff00" title="Highlight Color">
            </label>
        </div>

        <!-- Alignment -->
        <div class="toolbar-group">
            <button class="btn" title="Align Left" onclick="execCommand('justifyLeft')">⇐</button>
            <button class="btn" title="Align Center" onclick="execCommand('justifyCenter')">⇔</button>
            <button class="btn" title="Align Right" onclick="execCommand('justifyRight')">⇒</button>
            <button class="btn" title="Justify" onclick="execCommand('justifyFull')">☰</button>
        </div>

        <!-- Lists -->
        <div class="toolbar-group">
            <button class="btn" title="Bullet List" onclick="execCommand('insertUnorderedList')">• List</button>
            <button class="btn" title="Numbered List" onclick="execCommand('insertOrderedList')">1. List</button>
        </div>

        <!-- Links & Media -->
        <div class="toolbar-group">
            <button class="btn" title="Insert Link" onclick="insertLink()">🔗</button>
            <button class="btn" title="Insert Image" onclick="insertImage()">🖼️</button>
            <button class="btn" title="Insert Video" onclick="insertVideo()">🎬</button>
        </div>

        <!-- Code & Special -->
        <div class="toolbar-group">
            <button class="btn" title="Blockquote" onclick="execCommand('formatBlock', 'blockquote')">❝</button>
            <button class="btn" title="Code" onclick="execCommand('formatBlock', 'pre')">⟨⟩</button>
            <button class="btn" title="Horizontal Rule" onclick="execCommand('insertHorizontalRule')">—</button>
        </div>

        <!-- Heading -->
        <div class="toolbar-group">
            <select class="select" title="Heading" onchange="execHeading(this.value)">
                <option value="">Heading</option>
                <option value="h1">H1</option>
                <option value="h2">H2</option>
                <option value="h3">H3</option>
                <option value="h4">H4</option>
                <option value="h5">H5</option>
                <option value="h6">H6</option>
                <option value="p">Paragraph</option>
            </select>
        </div>

        <!-- Remove Formatting -->
        <div class="toolbar-group">
            <button class="btn" title="Remove Formatting" onclick="execCommand('removeFormat')">✕ Format</button>
        </div>
    </div>

    <textarea id="storyEditor" class="editor" placeholder="Start writing your story..."></textarea>
</div>

<script>
const editor = document.getElementById('storyEditor');

// Safe command execution
function execCommand(cmd, value = null) {
    try {
        if (value) {
            document.execCommand(cmd, false, value);
        } else {
            document.execCommand(cmd, false, null);
        }
    } catch (e) {
        console.error('Command error:', e);
    }
}

function execFontSize(size) {
    if (size) execCommand('fontSize', size);
}

function execFontFamily(font) {
    if (font) execCommand('fontName', font);
}

function execForeColor(color) {
    execCommand('foreColor', color);
}

function execBackColor(color) {
    execCommand('backColor', color);
}

function execHeading(tag) {
    if (tag) {
        if (tag === 'p') {
            execCommand('formatBlock', 'p');
        } else {
            execCommand('formatBlock', tag);
        }
    }
}

function execUndoRedo(action) {
    if (action === 'undo') {
        document.execCommand('undo', false, null);
    } else {
        document.execCommand('redo', false, null);
    }
}

function insertLink() {
    const url = prompt('Enter URL:');
    if (url) execCommand('createLink', url);
}

function insertImage() {
    const url = prompt('Enter image URL:');
    if (url) execCommand('insertImage', url);
}

function insertVideo() {
    const url = prompt('Enter video URL (YouTube/Vimeo):');
    if (url) {
        const iframe = '<iframe width="560" height="315" src="' + url + '" frameborder="0" allowfullscreen></iframe>';
        execCommand('insertHTML', iframe);
    }
}

// Enable contenteditable for the textarea area
document.addEventListener('DOMContentLoaded', function() {
    // Optional: You can convert the textarea to a contenteditable div for better WYSIWYG experience
    // For now, we're using the standard textarea with execCommand
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + B = Bold
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
        e.preventDefault();
        execCommand('bold');
    }
    // Ctrl/Cmd + I = Italic
    if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
        e.preventDefault();
        execCommand('italic');
    }
    // Ctrl/Cmd + U = Underline
    if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
        e.preventDefault();
        execCommand('underline');
    }
});

// Helper function to get editor content
function getEditorContent() {
    return editor.value;
}

// Helper function to set editor content
function setEditorContent(content) {
    editor.value = content;
}

// Helper function to get plain text
function getPlainText() {
    return editor.value;
}
</script>
