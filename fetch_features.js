function fetchFeatures() {
    fetch('fetch_features.php')
        .then(response => response.json())
        .then(data => {
            const featuresList = document.querySelector('.features-list');
            featuresList.innerHTML = '';

            data.forEach(feature => {
                const featureItem = document.createElement('div');
                featureItem.classList.add('feature-item');

                if (feature.photo) {
                    const img = document.createElement('img');
                    img.src = 'uploads/' + feature.photo;
                    img.alt = feature.title;
                    featureItem.appendChild(img);
                }

                const title = document.createElement('h3');
                title.textContent = feature.title;
                featureItem.appendChild(title);

                const description = document.createElement('p');
                description.textContent = feature.description;
                featureItem.appendChild(description);

                featuresList.appendChild(featureItem);
            });
        });
}

// Fetch features every 10 seconds
setInterval(fetchFeatures, 10000);
