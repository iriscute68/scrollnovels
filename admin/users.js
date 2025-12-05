function loadUsers() {
    let search = document.getElementById("searchUser").value;
    let role = document.getElementById("roleFilter").value;
    let ban = document.getElementById("banFilter").value;

    const xhr = new XMLHttpRequest();
    xhr.open("POST", "user_fetch.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onload = function () {
        document.getElementById("userTable").innerHTML = this.responseText;
    };

    xhr.send("search=" + encodeURIComponent(search) + "&role=" + encodeURIComponent(role) + "&ban=" + encodeURIComponent(ban));
}

document.getElementById("searchUser").onkeyup = loadUsers;
document.getElementById("roleFilter").onchange = loadUsers;
document.getElementById("banFilter").onchange = loadUsers;

loadUsers();

function banUser(id) {
    if (!confirm("Ban this user?")) return;

    fetch("user_ban.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "id=" + id
    }).then(r => r.text()).then(loadUsers);
}

function unbanUser(id) {
    fetch("user_unban.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "id=" + id
    }).then(r => r.text()).then(loadUsers);
}

function changeRole(id, role) {
    fetch("user_role_update.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "id=" + id + "&role=" + encodeURIComponent(role)
    }).then(r => r.text()).then(loadUsers);
}
