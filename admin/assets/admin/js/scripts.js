$(document).ready(function () {
    "use strict";

    if ($(".datatable").length > 0) {
        $('.datatable').DataTable();
    }

    if ($(".alert.alert-message").length > 0) {
        window.setTimeout(function () {
            $("#alert_message").fadeTo(500, 0).slideUp(500, function () {
                $(this).remove();
            });
        }, 3000);
    }

    if ($(".info-content").length > 0) {
        $('.addInfo').on("click", function () {

            var target = $(this).attr('data-target'),
                info_label = $('#info_label_' + target).val(),
                info_value = $('#info_value_' + target).val();

            if ((info_label.search('<') == -1) &&
                (info_label.search('>') == -1) &&
                (info_label.search('"') == -1) &&
                (info_value.search('<') == -1) &&
                (info_value.search('>') == -1) &&
                (info_value.search('"') == -1)) {

                var textIcon = '<span class="' + info_label + '"></span>';

                $('.table-' + target).append('<tr><td class="fw-bold">' + textIcon + '</td><td>' + info_value + '</td><td class="text-right"><button type="button" class="btn btn-outline-danger btn-sm rounded-circle deleteInfo" data-info="' + info_label + '" data-value="' + info_value + '"><i class="fas fa-times"></i></button></tr>');

                if ($("#" + target).val() != '') {
                    var listInfo = JSON.parse($("#" + target).val());
                } else {
                    var listInfo = [];
                }
                listInfo.push({
                    "title": info_label,
                    "text": info_value,
                });
                $("#" + target).val(JSON.stringify(listInfo));
            } else {
                $('.invalid-' + target).removeClass("d-none");
                setTimeout(function () {
                    $('.invalid-' + target).addClass("d-none");
                }, 2500);
            }
            $('#info_label_' + target).val('');
            $('#info_value_' + target).val('');

        });
    }

    $('.table-elements').on('click', 'button.deleteInfo', function () {
        var target = $(this).parent().parent().parent().parent().attr('data-target'),
            listInfo = JSON.parse($("#" + target).val()),
            title = $(this).attr('data-info'),
            text = $(this).attr('data-value');
        for (var i = 0; i < listInfo.length; i++) {
            if (title == listInfo[i]["title"] && text == listInfo[i]["text"]) {
                listInfo.splice(i, 1);
                $(this).parent().parent().remove();
                $("#" + target).val(JSON.stringify(listInfo));
            }
        }
    });

    $('.previewImage ').on("change", function () {
        var image = this.files[0],
            type = $(this).attr("name");
        if (image["type"] == "image/jpeg" || image["type"] == "image/jpg" || image["type"] == "image/png") {
            var dataImage = new FileReader();
            dataImage.readAsDataURL(image);
            $(dataImage).on("load", function (event) {
                var routeImage = event.target.result;
                $(".previewImage_" + type).attr("src", routeImage);
            });
        }
    });

    if ($(".openModal").length > 0) {
        var idModal = $(".openModal").attr('data-id'),
            myModal = new bootstrap.Modal(document.getElementById(idModal), {});
        myModal.show();
    }

    if ($("form.form-visibility").length > 0) {
        $('.form-check-input').on("change", function () {
            var target = $(this).attr('data-visibility');
            if (target != null) {
                if ($(this).prop("checked") == true) {
                    $('.' + target).removeClass('d-none');
                } else {
                    $('.' + target).addClass('d-none');
                }
            }
        });
    }

    $('[data-bs-toggle="tooltip"]').tooltip();

    $(document).on('click', '.ai-generate-btn', function () {
        var $btn = $(this);
        var type = $btn.data('type');
        var field = $btn.data('field');
        var inputName = $btn.data('input-name');
        var isSummernote = $btn.data('summernote') ? '1' : '0';

        // Take the prompt directly from the Summernote editor content (or text input)
        var promptContext = '';
        if (isSummernote === '1') {
            // Get raw text without HTML tags to use as the prompt
            var rawText = $('textarea[name="' + inputName + '"]').summernote('code');
            promptContext = $('<div>').html(rawText).text().trim();
        } else {
            promptContext = $('input[name="' + inputName + '"], textarea[name="' + inputName + '"]').val().trim();
        }

        if (!promptContext) {
            alert('Please enter some text in the content editor to use as a prompt for AI.');
            return;
        }

        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Generating...');

        fetch(window.adminAiGenerateUrl || '/admin/ai-generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ type: type, field: field, topic: promptContext })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                if (isSummernote === '1') {
                    $('textarea[name="' + inputName + '"]').summernote('code', data.text || '');
                } else {
                    $('input[name="' + inputName + '"], textarea[name="' + inputName + '"]').val(data.text || '');
                }
            })
            .catch(function () { alert('Request failed.'); })
            .finally(function () {
                $btn.prop('disabled', false).html(originalHtml);
            });
    });


    $(".summernote").each(function () {
        var $this = $(this),
            name = $this.attr('name'),
            folder = $this.attr('data-folder'),
            route = $this.attr('data-route'),
            code = $this.attr('data-code');
        $(this).summernote({
            height: 300,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['fontname', ['fontname']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture']],
                ['view', ['fullscreen', 'help']],
            ],
            callbacks: {
                onImageUpload: function (files) {
                    for (var i = 0; i < files.length; i++) {
                        upload(files[i], name, folder, route, code);
                    }
                }
            }
        });
        $('.check-summernote').on('click', function (e) {
            e.preventDefault();
            var html = $('.note-editor .note-editable').html();
            if (html) {
                html = html.replace(/<\/blockquote>/g, '</p>').replace(/<blockquote/g, '<p');
                $('.summernote').val(html);
            }
            $('form').trigger('submit');
        });
    });
    $(".summernote-simple").each(function () {
        $(".summernote-simple").summernote({
            height: 200,
            toolbar: [
                ['font', ['bold', 'underline', 'clear']],
                ['fontname', ['fontname']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['view', ['fullscreen', 'codeview', 'help']],
            ]
        });
        $('.check-summernote').on('click', function () {
            $('form').trigger('submit');
        });
    });

    function upload(file, name, folder, route, code) {
        var data = new FormData();
        data.append('file', file, file.name);
        data.append('folder', folder);
        data.append('code', code);
        data.append('_token', $('meta[name="csrf-token"]').attr('content'));
        $.ajax({
            url: window.summernoteUploadUrl || (route + "/admin/summernote/upload"),
            method: "POST",
            data: data,
            contentType: false,
            cache: false,
            processData: false,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (response) {
                $("[name='" + name + "']").summernote("insertImage", response, function () { });
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error(textStatus + "" + errorThrown);
            }
        });
    }

    $('.dropdown-toggle').dropdown();

    if ($(".form-password").length > 0) {
        $('.form-password i.password-visible').on("click", function () {
            $(this).parent().find('input').get(0).type = 'text';
            $(this).addClass('d-none');
            $(this).parent().find('.password-hidden').removeClass('d-none');
        });
        $('.form-password i.password-hidden').on("click", function () {
            $(this).parent().find('input').get(0).type = 'password';
            $(this).addClass('d-none');
            $(this).parent().find('.password-visible').removeClass('d-none');
        });
    }

    if ($(".remove-image").length > 0) {
        $('.remove-image').on("click", function () {
            var target = $(this).attr('data-target'),
                url = $(this).attr('data-url');
            $('input[name="' + target + '_current"]').val('');
            $('input[name="' + target + '"]').val('');
            if ($(".previewImage_" + target).length) {
                $(".previewImage_" + target).attr("src", url + "uploads/img/image_default.png");
            }
        });
    }
    if ($(".remove-file").length > 0) {
        $('.remove-file').on("click", function () {
            var target = $(this).attr('data-target');
            $('input[name="' + target + '_current"]').val('');
            $('input[name="' + target + '"]').val('');
        });
    }

    // Icon Picker Modal Logic
    if ($("#iconPickerModal").length > 0) {
        const iconGrid = $("#iconGrid");
        let allIcons = [];
        let categoriesData = {}; // Store categories and their icons

        // Load all available metadata to significantly improve searching and grouping
        Promise.all([
            fetch('/assets/metadata/icons.json').then(r => r.json()).catch(() => ({})),
            fetch('/assets/metadata/categories.yml').then(r => r.text()).catch(() => ""),
            fetch('/assets/metadata/sponsors.yml').then(r => r.text()).catch(() => ""),
            fetch('/assets/metadata/shims.json').then(r => r.json()).catch(() => ([]))
        ]).then(([iconsData, categoriesYml, sponsorsYml, shimsData]) => {
            
            // Pre-process categories
            let iconCategories = {};
            if (categoriesYml) {
                let currentCategory = '';
                let currentCategoryLabel = '';
                categoriesYml.split('\n').forEach(line => {
                    const catMatch = line.match(/^([a-z0-9-]+):$/);
                    if (catMatch) {
                        currentCategory = catMatch[1];
                        currentCategoryLabel = currentCategory.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                        categoriesData[currentCategoryLabel] = [];
                    } else if (currentCategory && line.includes('- ')) {
                        const iconName = line.split('- ')[1].trim().replace(/['"]/g, '');
                        if (!iconCategories[iconName]) iconCategories[iconName] = [];
                        iconCategories[iconName].push(currentCategoryLabel);
                    }
                });
            }

            // Other groups
            categoriesData["Brands"] = [];
            categoriesData["Uncategorized"] = [];

            // Pre-process sponsors
            let iconSponsors = {}; 
            if (sponsorsYml) {
                let currentSponsor = '';
                sponsorsYml.split('\n').forEach(line => {
                    const spMatch = line.match(/^([a-z0-9-]+):$/);
                    if (spMatch) {
                        currentSponsor = spMatch[1].replace(/-/g, ' ');
                    } else if (currentSponsor && line.includes('- ')) {
                        const iconName = line.split('- ')[1].trim().replace(/['"]/g, '');
                        if (!iconSponsors[iconName]) iconSponsors[iconName] = [];
                        iconSponsors[iconName].push(currentSponsor);
                    }
                });
            }

            // Pre-process shims to map old names to new icons
            let iconShims = {};
            if (Array.isArray(shimsData)) {
                shimsData.forEach(shim => {
                    if (!Array.isArray(shim)) return;
                    const oldName = shim[0];
                    const newName = shim[2];
                    if (typeof oldName === 'string' && typeof newName === 'string' && newName) {
                        if (!iconShims[newName]) iconShims[newName] = [];
                        iconShims[newName].push(oldName.replace(/-/g, ' '));
                    }
                });
            }

            // Base icons
            for (const [iconName, iconData] of Object.entries(iconsData)) {
                const styles = iconData.styles || [];
                let searchTerms = (iconData.search?.terms || []).join(' ');
                
                // Incorporate additional metadata for better searching
                if (iconCategories[iconName]) {
                    searchTerms += ' ' + iconCategories[iconName].join(' ');
                }

                if (iconSponsors[iconName]) {
                    searchTerms += ' sponsor ' + iconSponsors[iconName].join(' ');
                }

                if (iconShims[iconName]) {
                    searchTerms += ' ' + iconShims[iconName].join(' ');
                }
                
                // If it's a brand/sponsor, let's tag it for better searchability
                if (styles.includes('brands')) {
                    searchTerms += ' brand social';
                }

                styles.forEach(style => {
                    let prefix = 'fas';
                    if (style === 'brands') prefix = 'fab';
                    else if (style === 'regular') prefix = 'far';
                    else if (style === 'solid') prefix = 'fas';
                    else if (style === 'light') prefix = 'fal';
                    else if (style === 'duotone') prefix = 'fad';
                    else if (style === 'thin') prefix = 'fat';

                    const iconObj = { 
                        c: prefix + ' fa-' + iconName, 
                        n: iconName.replace(/-/g, ' '), 
                        t: searchTerms 
                    };

                    allIcons.push(iconObj);

                    // Assign to categories
                    if (style === 'brands') {
                        categoriesData["Brands"].push(iconObj);
                    } else if (iconCategories[iconName]) {
                        iconCategories[iconName].forEach(cat => {
                            if (categoriesData[cat]) {
                                categoriesData[cat].push(iconObj);
                            }
                        });
                    } else {
                        categoriesData["Uncategorized"].push(iconObj);
                    }
                });
            }

            // Deduplicate categories data
            for (const cat in categoriesData) {
                categoriesData[cat] = categoriesData[cat].filter((value, index, self) =>
                    index === self.findIndex((t) => (t.c === value.c))
                );
                // Remove empty categories
                if (categoriesData[cat].length === 0) {
                    delete categoriesData[cat];
                }
            }

            // Deduplicate allIcons
            allIcons = allIcons.filter((value, index, self) =>
              index === self.findIndex((t) => (t.c === value.c))
            );
            
            // Re-render immediately if search input is focused but empty
            if ($('#iconPickerModal').hasClass('show') && $('#iconSearchInput').val().trim() === '') {
                renderCategoryButtons();
                renderIcons();
            }
        });

        let selectedCategory = null;
        const categoryFilterContainer = $('<div class="mb-3 d-flex flex-wrap gap-1 align-items-center" id="iconCategoryFilters" style="max-height: 120px; overflow-y: auto;"></div>');
        iconGrid.before(categoryFilterContainer);

        const renderCategoryButtons = () => {
            categoryFilterContainer.empty();
            const allBtn = $('<button>', {
                type: 'button',
                class: 'btn btn-sm ' + (selectedCategory === null ? 'btn-primary' : 'btn-outline-primary'),
                text: 'All'
            }).on('click', function() {
                selectedCategory = null;
                renderCategoryButtons();
                renderIcons($('#iconSearchInput').val());
            });
            categoryFilterContainer.append(allBtn);

            for (const cat of Object.keys(categoriesData).sort()) {
                const btn = $('<button>', {
                    type: 'button',
                    class: 'btn btn-sm ' + (selectedCategory === cat ? 'btn-primary' : 'btn-outline-primary'),
                    text: cat
                }).on('click', function() {
                    selectedCategory = selectedCategory === cat ? null : cat;
                    renderCategoryButtons();
                    renderIcons($('#iconSearchInput').val());
                });
                categoryFilterContainer.append(btn);
            }
        };

        const createIconButton = (icon) => {
            const btn = $('<button>', {
                type: 'button',
                class: 'btn btn-outline-secondary m-1 px-3 py-2',
                title: icon.n,
                html: '<i class="' + icon.c + ' fa-lg"></i>'
            });
            btn.on('click', function () {
                $('#info_label_social-links').val(icon.c);
                $('#icon-picker-preview').attr('class', icon.c);
                $('#iconPickerModal').modal('hide');
            });
            return btn;
        };

        const renderIcons = (query = '') => {
            iconGrid.empty();
            const lowerQuery = query.toLowerCase().trim();

            if (selectedCategory !== null) {
                // Show only selected category
                const icons = categoriesData[selectedCategory] || [];
                let filtered = icons;
                
                if (lowerQuery !== '') {
                    filtered = icons.filter(icon =>
                        icon.n.toLowerCase().includes(lowerQuery) ||
                        icon.c.toLowerCase().includes(lowerQuery) ||
                        (icon.t && icon.t.toLowerCase().includes(lowerQuery))
                    );
                }

                if (filtered.length > 0) {
                    filtered.forEach(icon => {
                        iconGrid.append(createIconButton(icon));
                    });
                } else {
                    iconGrid.append($('<p>', { class: 'text-muted mt-3', text: 'No icons found in this category.' }));
                }
            } else {
                if (lowerQuery === '') {
                    // Show grouped by category
                    for (const cat of Object.keys(categoriesData).sort()) {
                        const icons = categoriesData[cat];
                        if (icons.length > 0) {
                            iconGrid.append($('<h5>', { class: 'w-100 mt-3 mb-2 border-bottom pb-1', text: cat }));
                            icons.forEach(icon => {
                                iconGrid.append(createIconButton(icon));
                            });
                        }
                    }
                } else {
                    // Show flat search results
                    const filtered = allIcons.filter(icon =>
                        icon.n.toLowerCase().includes(lowerQuery) ||
                        icon.c.toLowerCase().includes(lowerQuery) ||
                        (icon.t && icon.t.toLowerCase().includes(lowerQuery))
                    );

                    if (filtered.length > 0) {
                        filtered.forEach(icon => {
                            iconGrid.append(createIconButton(icon));
                        });
                    } else {
                        iconGrid.append($('<p>', { class: 'text-muted mt-3', text: 'No icons found.' }));
                    }
                }
            }
        };

        $('#iconPickerModal').on('shown.bs.modal', function () {
            $('#iconSearchInput').val('');
            selectedCategory = null;
            if (Object.keys(categoriesData).length > 0) {
                renderCategoryButtons();
            }
            renderIcons();
            $('#iconSearchInput').trigger('focus');
        });

        $('#iconSearchInput').on('input', function () {
            renderIcons($(this).val());
        });

        $('#info_label_social-links').on('input', function () {
            const val = $(this).val();
            if (val.length > 3) {
                $('#icon-picker-preview').attr('class', val);
            } else {
                $('#icon-picker-preview').attr('class', 'fas fa-icons');
            }
        });
    }

});

if ($(".popup-image").length > 0) {
    $('.popup-image').magnificPopup({
        type: 'image',
        removalDelay: 500,
        callbacks: {
            beforeOpen: function () {
                this.st.image.markup = this.st.image.markup.replace('mfp-figure', 'mfp-figure mfp-with-anim');
                this.st.mainClass = 'mfp-zoom-in';
            }
        },
        closeOnContentClick: true,
        fixedContentPos: false
    });
}
