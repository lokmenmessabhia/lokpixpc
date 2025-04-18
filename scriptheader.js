document.addEventListener('DOMContentLoaded', function() {
    const profileMenu = document.querySelector('.profile-menu');
    const dropdownMenu = document.querySelector('.dropdown-menu');

    profileMenu.addEventListener('click', function() {
        dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
    });

    document.addEventListener('click', function(event) {
        if (!profileMenu.contains(event.target)) {
            dropdownMenu.style.display = 'none';
        }
    });
});
