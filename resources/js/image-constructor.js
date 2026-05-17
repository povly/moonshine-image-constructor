document.addEventListener('alpine:init', () => {
    Alpine.store('ic', {
        editor: null,
        file: null,

        async open(file) {
            this.file = file;

            window.MoonShine?.ui?.toggleModal('image-constructor');
            await new Promise((r) => setTimeout(r, 300));

            const container = document.getElementById('ie-container');
            if (!container) {
                return;
            }

            this._terminate();

            const FilerobotImageEditor = window.FilerobotImageEditor;
            if (!FilerobotImageEditor) {
                window.MoonShine?.ui?.toast('Image editor not loaded', 'error');
                return;
            }

            const config = window.ImageConstructorConfig || {};

            const editorConfig = {
                source: file.url,
                defaultSavedImageType: config.defaultSaveType || 'png',
                defaultSavedImageQuality: (config.defaultSaveQuality || 92) / 100,
                defaultSavedImageName: file.name || undefined,
                tabsIds: config.tabs || [],
                defaultTabId: config.defaultTab || undefined,
                defaultToolId: config.defaultTool || undefined,
                theme: config.theme || {},
                annotationsCommon: {
                    fill: '#ffffff',
                    stroke: '#000000',
                    strokeWidth: 0,
                    shadowOffsetX: 0,
                    shadowOffsetY: 0,
                    shadowBlur: 0,
                    shadowColor: '#000000',
                    shadowOpacity: 1,
                    opacity: 1,
                },
                Text: {
                    text: 'Text...',
                    fontFamily: 'Arial',
                    fontSize: 32,
                    fill: '#ffffff',
                    fontStyle: 'bold',
                    shadowOffsetX: 1,
                    shadowOffsetY: 1,
                    shadowBlur: 4,
                    shadowColor: '#000000',
                    shadowOpacity: 0.6,
                },
                Crop: {
                    presetsItems: [
                        { titleKey: 'free', descriptionKey: 'Free', ratio: null },
                        { titleKey: 'square', descriptionKey: '1:1', ratio: 1 },
                        { titleKey: 'landscape4:3', descriptionKey: '4:3', ratio: 4 / 3 },
                        { titleKey: 'landscape16:9', descriptionKey: '16:9', ratio: 16 / 9 },
                        { titleKey: 'portrait3:4', descriptionKey: '3:4', ratio: 3 / 4 },
                        { titleKey: 'portrait9:16', descriptionKey: '9:16', ratio: 9 / 16 },
                    ],
                },
                closeAfterSave: false,
                avoidChangesNotSavedAlertOnLeave: true,
                onBeforeSave: (isCancel) => {
                    return !isCancel;
                },
                onSave: (editedImageObject, designState) => {
                    this._saveToServer(editedImageObject);
                },
            };

            if (config.watermarkGallery && config.watermarkGallery.length > 0) {
                editorConfig.Watermark = {
                    gallery: config.watermarkGallery,
                    imageScalingRatio: 0.33,
                };
            }

            this.editor = new FilerobotImageEditor(container, editorConfig);
            this.editor.render({
                onClose: (closingReason) => {
                    this.close();
                },
            });
        },

        async _saveToServer(editedImageObject) {
            const config = window.ImageConstructorConfig || {};

            try {
                const base64 = editedImageObject.imageBase64;
                const fullName = editedImageObject.fullName || 'edited.png';

                const res = await fetch(base64);
                const blob = await res.blob();

                const formData = new FormData();
                formData.append('source_path', this.file?.path || '');
                formData.append('image', blob, fullName);

                const csrfToken =
                    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                const response = await fetch(config.saveUrl || '', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });

                const data = await response.json();

                if (data.status) {
                    window.MoonShine?.ui?.toast('Image saved', 'success');
                    window.dispatchEvent(new CustomEvent('mm:refresh'));
                    this.close();
                } else {
                    window.MoonShine?.ui?.toast(data.message || 'Save failed', 'error');
                }
            } catch (e) {
                window.MoonShine?.ui?.toast(e.message || 'Save failed', 'error');
            }
        },

        close() {
            this._terminate();
            this.file = null;

            const container = document.getElementById('ie-container');
            if (container) {
                container.innerHTML = '';
            }

            window.MoonShine?.ui?.toggleModal('image-constructor');
        },

        _terminate() {
            if (this.editor) {
                try {
                    this.editor.terminate();
                } catch (e) {
                    // ignore termination errors
                }
                this.editor = null;
            }
        },
    });
});
