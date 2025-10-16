// Model Translation Management
document.addEventListener('DOMContentLoaded', function() {
    const modelSelect = document.getElementById('modelClassSelect');
    const modelInfoCard = document.getElementById('modelInfoCard');
    const modelRecordCount = document.getElementById('modelRecordCount');
    const modelFieldsList = document.getElementById('modelFieldsList');
    const modelRecordCard = document.getElementById('modelRecordCard');
    const statRecordCount = document.getElementById('statRecordCount');

    const checkboxes = document.querySelectorAll('.target-locale-checkbox');
    const selectAllBtn = document.getElementById('selectAllLanguages');
    const deselectAllBtn = document.getElementById('deselectAllLanguages');
    const selectedContainer = document.getElementById('selectedLanguagesBadges');
    const selectedList = document.getElementById('selectedLanguagesList');
    const selectedCount = document.getElementById('selectedCount');
    const translateBtn = document.getElementById('translateButton');
    const overwriteCheckbox = document.getElementById('overwrite_models');
    const whenUnchecked = document.querySelector('.when-unchecked');
    const whenChecked = document.querySelector('.when-checked');
    const estimateCard = document.getElementById('translationEstimate');
    const estimateCount = document.getElementById('estimateCount');

    // Model selection handler
    modelSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];

        if (this.value) {
            const recordCount = selectedOption.dataset.recordCount;
            const fields = JSON.parse(selectedOption.dataset.fields || '{}');
            const fieldNames = Object.keys(fields).join(', ');

            modelRecordCount.textContent = parseInt(recordCount).toLocaleString();
            modelFieldsList.textContent = fieldNames || 'None';
            modelInfoCard.style.display = 'block';

            statRecordCount.textContent = parseInt(recordCount).toLocaleString();
            modelRecordCard.style.display = 'block';

            updateTranslateButton();
        } else {
            modelInfoCard.style.display = 'none';
            modelRecordCard.style.display = 'none';
            updateTranslateButton();
        }
    });

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

            // Update estimate if model is selected
            if (modelSelect.value) {
                const selectedOption = modelSelect.options[modelSelect.selectedIndex];
                const recordCount = parseInt(selectedOption.dataset.recordCount || 0);
                const fields = JSON.parse(selectedOption.dataset.fields || '{}');
                const fieldCount = Object.keys(fields).length;

                const estimate = recordCount * selected.length * fieldCount;
                estimateCount.textContent = estimate.toLocaleString();
                estimateCard.style.display = 'block';
            }
        } else {
            selectedContainer.style.display = 'none';
            estimateCard.style.display = 'none';
        }

        updateTranslateButton();
    }

    // Update translate button state
    function updateTranslateButton() {
        const hasModel = modelSelect.value !== '';
        const hasLanguages = Array.from(checkboxes).some(cb => cb.checked);
        translateBtn.disabled = !(hasModel && hasLanguages);
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
