// Language Selection Management
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.target-locale-checkbox');
    const selectAllBtn = document.getElementById('selectAllLanguages');
    const deselectAllBtn = document.getElementById('deselectAllLanguages');
    const selectedContainer = document.getElementById('selectedLanguagesBadges');
    const selectedList = document.getElementById('selectedLanguagesList');
    const selectedCount = document.getElementById('selectedCount');
    const translateBtn = document.getElementById('translateButton');
    const overwriteCheckbox = document.getElementById('overwrite_existing');
    const whenUnchecked = document.querySelector('.when-unchecked');
    const whenChecked = document.querySelector('.when-checked');
    const estimateCard = document.getElementById('translationEstimate');
    const estimateCount = document.getElementById('estimateCount');

    // Get totalMessages from the page (set by PHP)
    const totalMessages = parseInt(estimateCount.dataset.totalMessages || 0);

    // Update selected languages display
    function updateSelectedLanguages() {
        const selected = Array.from(checkboxes).filter(cb => cb.checked);
        selectedCount.textContent = selected.length;

        if (selected.length > 0) {
            selectedContainer.style.display = 'block';
            selectedList.innerHTML = selected.map(cb => {
                const name = cb.dataset.localeName;
                const code = cb.dataset.localeCode;
                return `
                    <div class="selected-language-badge">
                        <span class="name">${name}</span>
                        <span class="code">${code}</span>
                        <span class="remove" data-locale="${cb.value}">×</span>
                    </div>
                `;
            }).join('');

            translateBtn.disabled = false;

            // Update estimate
            const estimate = totalMessages * selected.length;
            estimateCount.textContent = estimate.toLocaleString();
            estimateCard.style.display = 'block';
        } else {
            selectedContainer.style.display = 'none';
            translateBtn.disabled = true;
            estimateCard.style.display = 'none';
        }
    }

    // Checkbox change handler
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectedLanguages);
    });

    // Select all handler
    selectAllBtn.addEventListener('click', function() {
        checkboxes.forEach(cb => cb.checked = true);
        updateSelectedLanguages();
    });

    // Deselect all handler
    deselectAllBtn.addEventListener('click', function() {
        checkboxes.forEach(cb => cb.checked = false);
        updateSelectedLanguages();
    });

    // Remove badge handler (using event delegation)
    selectedList.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove')) {
            const localeCode = e.target.dataset.locale;
            const checkbox = document.querySelector(`input[value="${localeCode}"]`);
            if (checkbox) {
                checkbox.checked = false;
                updateSelectedLanguages();
            }
        }
    });

    // Overwrite toggle handler
    overwriteCheckbox.addEventListener('change', function() {
        if (this.checked) {
            whenUnchecked.style.display = 'none';
            whenChecked.style.display = 'block';
        } else {
            whenUnchecked.style.display = 'block';
            whenChecked.style.display = 'none';
        }
    });

    // Initial state
    updateSelectedLanguages();
});
