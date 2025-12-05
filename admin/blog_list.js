function loadBlogs() {
    let search = document.getElementById("blogSearch").value;
    let status = document.getElementById("blogStatus").value;

    fetch("blog_fetch.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "search=" + encodeURIComponent(search) + "&status=" + encodeURIComponent(status)
    })
    .then(res => res.text())
    .then(html => document.getElementById("blogTable").innerHTML = html);
}

document.getElementById("blogSearch").onkeyup = loadBlogs;
document.getElementById("blogStatus").onchange = loadBlogs;

loadBlogs();

function deleteBlog(id) {
    if (!confirm("Delete this blog permanently?")) return;

    fetch("blog_delete.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "id=" + id
    }).then(() => loadBlogs());
}

function publishBlog(id) {
    fetch("blog_publish.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "id=" + id
    }).then(() => loadBlogs());
}
