document.addEventListener('DOMContentLoaded', (event) => {
    const phoneNumberModal = document.getElementById('phoneNumberModal');
    const orderSummaryModal = document.getElementById('orderSummaryModal');
    const closeButtons = document.querySelectorAll('.close-button');
    const phoneForm = document.getElementById('phoneForm');
    const orderDetails = document.getElementById('orderDetails');
    const mainContent = document.getElementById('mainContent');

    // Show phone number modal
    phoneNumberModal.style.display = 'block';

    phoneForm.addEventListener('submit', function (event) {
        event.preventDefault();
        
        const phoneNumber = document.getElementById('phoneNumber').value;
        
        if (phoneNumber.match(/^\d{10}$/)) {
            // Hide phone number modal and show order summary
            phoneNumberModal.style.display = 'none';
            mainContent.style.filter = 'blur(5px)'; // Apply blur effect to main content

            // Populate order summary
            orderDetails.innerHTML = `
                <p>Product Name: Example Product</p>
                <p>Price: $100.00</p>
                <p>Phone Number: ${phoneNumber}</p>
            `;
            orderSummaryModal.style.display = 'block';
        } else {
            alert('Please enter a valid phone number.');
        }
    });

    // Close modals when close button is clicked
    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            phoneNumberModal.style.display = 'none';
            orderSummaryModal.style.display = 'none';
            mainContent.style.filter = 'none'; // Remove blur effect
        });
    });

    // Optional: Close modals when clicking outside of the modal content
    window.addEve
