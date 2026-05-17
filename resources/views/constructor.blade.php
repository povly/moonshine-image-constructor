@props([
    'config' => [],
])

<script>window.ImageConstructorConfig = {{ Js::from($config) }};</script>
<script src="{{ asset('vendor/image-constructor/filerobot-image-editor.min.js') }}"></script>
<script src="{{ asset('vendor/image-constructor/image-constructor.js') }}"></script>

<x-moonshine::modal
    name="image-constructor"
    title="Image Editor"
    :wide="true"
>
    <div id="ie-container" style="width: 100%; height: 85vh;"></div>
    <style>
        .FIE_tab-label { text-align: center; }
    </style>
</x-moonshine::modal>
