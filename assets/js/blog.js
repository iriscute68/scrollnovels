function submitBlogForm() {
    const form = document.getElementById('blogForm');
    const formData = new FormData(form);
    fetch('/pages/blog_new.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.href = '/pages/blog_list.php';
        } else {
            alert(data.message);
        }
    })
    .catch(err => {
        alert('Error submitting blog: ' + err);
    });
}

function displayBlogList() {
    fetch('/pages/blog_list.php')
    .then(response => response.json())
    .then(data => {
        const blogList = data.blogs;
        const container = document.getElementById('blogContainer');
        container.innerHTML = '';
        blogList.forEach(blog => {
            const blogItem = document.createElement('div');
            blogItem.innerHTML = `
                <h3>${blog.title}</h3>
                <p>${blog.content}</p>
                <p>${blog.author}</p>
            `;
            container.appendChild(blogItem);
        });
    })
    .catch(err => {
        console.error('Error fetching blog list: ' + err);
    });
}

displayBlogList();
