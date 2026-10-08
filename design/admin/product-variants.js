let colorEntries = [];
let colorFiles = {};

function initColors(initial) {
    if (!initial || !initial.length) return;
    colorEntries = initial.map((c) => ({
        name: c.name,
        imageUrl: '',
        serverUrl: c.image_url || ''
    }));
}

function addColorEntry(name) {
    if (!name) {
        const input = document.getElementById('colorInput');
        name = input.value.trim();
        input.value = '';
    }
    if (!name) return;
    if (colorEntries.find(c => c.name.toLowerCase() === name.toLowerCase())) return;
    colorEntries.push({ name, imageUrl: '', serverUrl: '' });
    renderColorList();
    updateColorsData();
    updateColorChips();
}

function removeColorEntry(index) {
    delete colorFiles[index];
    colorEntries.splice(index, 1);
    const newColorFiles = {};
    Object.keys(colorFiles).forEach(k => {
        const ki = parseInt(k);
        if (ki > index) newColorFiles[ki - 1] = colorFiles[k];
        else if (ki < index) newColorFiles[ki] = colorFiles[k];
    });
    colorFiles = newColorFiles;
    renderColorList();
    updateColorsData();
    updateColorChips();
}

function renderColorList() {
    const list = document.getElementById('colorVariantList');
    list.innerHTML = '';

    if (colorEntries.length === 0) {
        list.innerHTML = '<div style="color:var(--muted);font-size:var(--fs-sm);padding:var(--space-sm);">No colors added yet. Use the input or quick-add buttons below.</div>';
        return;
    }

    colorEntries.forEach((entry, i) => {
        const displayImage = entry.imageUrl || entry.serverUrl;
        const hasImage = !!displayImage;
        const card = document.createElement('div');
        card.className = 'color-variant-card';
        card.innerHTML = `
            <div class="color-variant-thumb" onclick="triggerColorImageUpload(${i})" title="Click to upload image">
                ${hasImage ? `<img src="${displayImage}" style="display:block;">` : '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="M346-140 100-386q-10-10-15-22t-5-25q0-13 5-25t15-22l230-229-106-106 62-65 400 400q10 10 14.5 22t4.5 25q0 13-4.5 25T686-386L440-140q-10 10-22 15t-25 5q-13 0-25-5t-22-15Zm47-506L179-432h428L393-646Zm399 526q-36 0-61-25.5T706-208q0-27 13.5-51t30.5-47l42-54 44 54q16 23 30 47t14 51q0 37-26 62.5T792-120Z"/></svg>'}
            </div>
            <div class="color-variant-name">${entry.name}</div>
            <label class="color-variant-upload-label" onclick="triggerColorImageUpload(${i})">
                ${hasImage ? 'Change image' : 'Upload image'}
            </label>
            <input type="file"
                    name="color_image_${i}"
                    id="colorFile_${i}"
                    accept="image/*"
                    style="display:none;"
                    onchange="handleColorImageChange(this, ${i})">
            <button type="button" class="color-variant-remove" onclick="removeColorEntry(${i})">×</button>
        `;
        list.appendChild(card);

        if (colorFiles[i]) {
            const dt = new DataTransfer();
            dt.items.add(colorFiles[i]);
            document.getElementById('colorFile_' + i).files = dt.files;
        }
    });
}

function triggerColorImageUpload(index) {
    document.getElementById('colorFile_' + index)?.click();
}

function handleColorImageChange(input, index) {
    const file = input.files[0];
    if (!file) return;
    colorFiles[index] = file;
    const reader = new FileReader();
    reader.onload = e => {
        colorEntries[index].imageUrl = e.target.result;
        renderColorList();
        updateColorsData();
    };
    reader.readAsDataURL(file);
}

function updateColorsData() {
    const data = colorEntries.map((c) => ({
        name: c.name,
        image_url: c.serverUrl || ''
    }));
    document.getElementById('colorsData').value = JSON.stringify(data);
}

function updateColorChips() {
    document.querySelectorAll('#productForm .suggestion-chip').forEach(chip => {
        chip.classList.toggle(
            'chip-added',
            colorEntries.some(c => c.name.toLowerCase() === chip.textContent.trim().toLowerCase())
        );
    });
}

document.getElementById('colorInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); addColorEntry(); }
});


let sizes = [];

function initSizes(initial) {
    if (!initial || !initial.length) return;
    sizes = [...initial];
}

function addSize(value) {
    value = value.trim();
    if (!value || sizes.includes(value)) return;
    sizes.push(value);
    renderSizeTags();
    updateSizesHidden();
}

function removeSize(value) {
    const i = sizes.indexOf(value);
    if (i > -1) sizes.splice(i, 1);
    renderSizeTags();
    updateSizesHidden();
}

function addSizeFromInput() {
    document.getElementById('sizeInput').value
        .split(',')
        .map(v => v.trim())
        .filter(Boolean)
        .forEach(addSize);
    document.getElementById('sizeInput').value = '';
}

function renderSizeTags() {
    const container = document.getElementById('sizeTags');
    container.innerHTML = '';
    sizes.forEach(v => {
        const tag = document.createElement('span');
        tag.className = 'variant-tag';
        tag.innerHTML = `${v} <button type="button" onclick="removeSize('${v}')">×</button>`;
        container.appendChild(tag);
    });
    document.querySelectorAll('#sizeBuilder .suggestion-chip').forEach(chip => {
        chip.classList.toggle('chip-added', sizes.includes(chip.textContent.trim()));
    });
}

function updateSizesHidden() {
    document.getElementById('sizesHidden').value = sizes.join(',');
}

document.getElementById('sizeInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addSizeFromInput(); }
});


document.getElementById('image-input')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('filePreviewImg').src = e.target.result;
        document.getElementById('filePreview').style.display = 'block';
    };
    reader.readAsDataURL(file);
});

document.getElementById('image-url-input')?.addEventListener('input', function() {
    const url = this.value.trim();
    const img = document.getElementById('urlPreviewImg');
    if (url) {
        img.src = url;
        img.style.display = 'block';
        document.getElementById('urlPreview').style.display = 'block';
        img.onerror = () => { img.style.display = 'none'; };
    } else {
        img.style.display = 'none';
    }
});


document.querySelector('form')?.addEventListener('submit', function() {
    Object.keys(colorFiles).forEach(i => {
        const input = document.getElementById('colorFile_' + i);
        if (input && colorFiles[i]) {
            const dt = new DataTransfer();
            dt.items.add(colorFiles[i]);
            input.files = dt.files;
        }
    });
});


if (window.INITIAL_COLORS) initColors(window.INITIAL_COLORS);
if (window.INITIAL_SIZES)  initSizes(window.INITIAL_SIZES);

renderColorList();
renderSizeTags();
updateColorsData();
updateColorChips();