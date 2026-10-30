@if($errors->has($name))<p id="{{ $id ?? $name }}-error" class="field-error" role="alert">{{ $errors->first($name) }}</p>@endif
