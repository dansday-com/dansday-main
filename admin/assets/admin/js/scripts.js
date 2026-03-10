$(document).ready(function() {
    "use strict";

    if ($(".datatable").length > 0) {
        $('.datatable').DataTable();
    }

    if ($(".alert.alert-message").length > 0) {
        window.setTimeout(function() {
            $("#alert_message").fadeTo(500, 0).slideUp(500, function() {
                $(this).remove();
            });
        }, 3000);
    }

    if ($(".info-content").length > 0) {
        $('.addInfo').on("click", function() {

            var target = $(this).attr('data-target'),
                info_label = $('#info_label_' + target).val(),
                info_value = $('#info_value_' + target).val();

            if ((info_label.search('<') == -1) &&
                (info_label.search('>') == -1) &&
                (info_label.search('"') == -1) &&
                (info_value.search('<') == -1) &&
                (info_value.search('>') == -1) &&
                (info_value.search('"') == -1)) {

                if ($('#info_label_' + target).hasClass('select-social') == true) {
                    var textIcon = '<span class="' + info_label + '"></span>';
                } else {
                    var textIcon = info_label;
                }

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
                setTimeout(function() {
                    $('.invalid-' + target).addClass("d-none");
                }, 2500);
            }
            $('#info_label_' + target).val('');
            $('#info_value_' + target).val('');

        });
    }

    $('.table-elements').on('click', 'button.deleteInfo', function() {
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

    $('.previewImage ').on("change", function() {
        var image = this.files[0],
            type = $(this).attr("name");
        if (image["type"] == "image/jpeg" || image["type"] == "image/jpg" || image["type"] == "image/png") {
            var dataImage = new FileReader();
            dataImage.readAsDataURL(image);
            $(dataImage).on("load", function(event) {
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
        $('.form-check-input').on("change", function() {
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

    function loadAiModalModels() {
        var modelsUrl = window.adminAiModelsUrl || '/admin/ai-models';
        return fetch(modelsUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var list = data.models || [];
                var $sel = $('#ai_modal_model');
                $sel.find('option:not(:first)').remove();
                list.forEach(function(m) {
                    $sel.append($('<option></option>').attr('value', m.id).text(m.name));
                });
                if (list.length > 0) {
                    $sel.val(list[0].id);
                }
            })
            .catch(function() {});
    }

    $(document).on('click', '.ai-generate-btn', function() {
        var $btn = $(this);
        $('#ai_modal_type').val($btn.data('type'));
        $('#ai_modal_field').val($btn.data('field'));
        $('#ai_modal_input_name').val($btn.data('input-name'));
        $('#ai_modal_summernote').val($btn.data('summernote') ? '1' : '0');
        $('#ai_modal_prompt').val('');
        var modalEl = document.getElementById('aiGenerateModal');
        if (modalEl) {
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
            if ($('#ai_modal_model option').length <= 1) {
                loadAiModalModels();
            }
        }
    });

    $('#ai_modal_generate_btn').on('click', function() {
        var type = $('#ai_modal_type').val();
        var field = $('#ai_modal_field').val();
        var inputName = $('#ai_modal_input_name').val();
        var isSummernote = $('#ai_modal_summernote').val() === '1';
        var prompt = ($('#ai_modal_prompt').val() || '').trim();
        var model = $('#ai_modal_model').val();
        if (!model) {
            alert('Please select a model.');
            return;
        }
        var $btn = $('#ai_modal_generate_btn');
        $btn.prop('disabled', true).text('…');
        fetch(window.adminAiGenerateUrl || '/admin/ai-generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ type: type, field: field, topic: prompt, model: model })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                alert(data.error);
                return;
            }
            if (isSummernote) {
                $('textarea[name="' + inputName + '"]').summernote('code', data.text || '');
            } else {
                $('input[name="' + inputName + '"], textarea[name="' + inputName + '"]').val(data.text || '');
            }
            var modalEl = document.getElementById('aiGenerateModal');
            if (modalEl) {
                bootstrap.Modal.getInstance(modalEl).hide();
            }
        })
        .catch(function() { alert('Request failed.'); })
        .finally(function() {
            $btn.prop('disabled', false).text($btn.data('label'));
        });
    });


    $(".summernote").each(function() {
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
                onImageUpload: function(files) {
                    for (var i = 0; i < files.length; i++) {
                        upload(files[i], name, folder, route, code);
                    }
                }
            }
        });
        $('.check-summernote').on('click', function(e) {
            e.preventDefault();
            var html = $('.note-editor .note-editable').html();
            if (html) {
                html = html.replace(/<\/blockquote>/g, '</p>').replace(/<blockquote/g, '<p');
                $('.summernote').val(html);
            }
            $('form').trigger('submit');
        });
    });
    $(".summernote-simple").each(function() {
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
        $('.check-summernote').on('click', function() {
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
            success: function(response) {
                $("[name='" + name + "']").summernote("insertImage", response, function() {});
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error(textStatus + "" + errorThrown);
            }
        });
    }

    $('.dropdown-toggle').dropdown();

    if ($(".form-password").length > 0) {
        $('.form-password i.password-visible').on("click", function() {
            $(this).parent().find('input').get(0).type = 'text';
            $(this).addClass('d-none');
            $(this).parent().find('.password-hidden').removeClass('d-none');
        });
        $('.form-password i.password-hidden').on("click", function() {
            $(this).parent().find('input').get(0).type = 'password';
            $(this).addClass('d-none');
            $(this).parent().find('.password-visible').removeClass('d-none');
        });
    }

    if ($(".remove-image").length > 0) {
        $('.remove-image').on("click", function() {
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
        $('.remove-file').on("click", function() {
            var target = $(this).attr('data-target');
            $('input[name="' + target + '_current"]').val('');
            $('input[name="' + target + '"]').val('');
        });
    }

});

if ($(".popup-image").length > 0) {
    $('.popup-image').magnificPopup({
        type: 'image',
        removalDelay: 500,
        callbacks: {
            beforeOpen: function() {
                this.st.image.markup = this.st.image.markup.replace('mfp-figure', 'mfp-figure mfp-with-anim');
                this.st.mainClass = 'mfp-zoom-in';
            }
        },
        closeOnContentClick: true,
        fixedContentPos: false
    });
}


