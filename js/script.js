document.addEventListener('DOMContentLoaded', function () {
    // Confirm before submitting status change
    const updateForms = document.querySelectorAll('form.needs-validation');
    updateForms.forEach(form => {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                if (form.querySelector('#status') && form.querySelector('#status').value === 'Closed') {
                    if (!confirm('Are you sure you want to close this ticket?')) {
                        event.preventDefault();
                    }
                }
            }
            form.classList.add('was-validated');
        });
    });
});