@props([
    'config' => [],
])

<script>window.ImageEditorConfig = {{ Js::from($config) }};</script>

<x-moonshine::modal
    name="image-editor"
    title="{{ __('image-editor::image-editor.image_editor') }}"
    :wide="true"
>
    <div id="ie-toolbar">
        <label for="ic-format">{{ __('image-editor::image-editor.save_as') }}:</label>
        <select id="ic-format">
            @foreach($config['available_formats'] ?? ['png', 'jpg'] as $fmt)
                <option value="{{ $fmt }}">{{ strtoupper($fmt) }}</option>
            @endforeach
        </select>
    </div>
    <div id="ie-container"></div>
</x-moonshine::modal>
