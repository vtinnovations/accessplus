document.addEventListener("DOMContentLoaded", function () {
    const headers = document.querySelectorAll('.card-header');
    headers.forEach(header => {
        header.classList.add('box-accodian');
        header.addEventListener('click', function () {
            const targetBody = this.nextElementSibling;
            if (targetBody.classList.contains('show')) {
                targetBody.classList.remove('show');
                this.classList.add('box-accodian');
                return;
            }
            document.querySelectorAll('.collapse').forEach(body => {
                body.classList.remove('show');
            });
            headers.forEach(h => h.classList.add('box-accodian'));
            targetBody.classList.add('show');
            this.classList.remove('box-accodian');
        });
    });
    const form = document.querySelector('.tl_wave_form');
    const loader = document.querySelector('.loding_content.ajax_loding_accessplus');
    const select = document.querySelector('#all-root-page');

    if (form && loader && select) {
        form.addEventListener('submit', function (e) {
            if (!select.value){
                e.preventDefault();
                select.focus();
                return;
            }
            loader.style.display = 'flex';
        });
    }
});
