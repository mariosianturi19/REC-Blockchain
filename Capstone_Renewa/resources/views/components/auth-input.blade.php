@props([
    'type' => 'text',
    'name',
    'label',
    'placeholder' => '',
    'required' => false,
    'icon' => null,
    'value' => ''
])

<div class="space-y-2">
    <label for="{{ $name }}" class="block text-sm font-semibold text-gray-700">
        @if($icon)
            <i class="fas fa-{{ $icon }} mr-2 text-gray-400"></i>
        @endif
        {{ $label }}
        @if($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    
    @if($type === 'password')
        <div class="relative">
            <input 
                id="{{ $name }}" 
                type="password" 
                name="{{ $name }}" 
                value="{{ old($name, $value) }}"
                {{ $required ? 'required' : '' }}
                {{ $attributes->merge(['class' => 'modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400 pr-12']) }}
                placeholder="{{ $placeholder }}"
            >
            <button 
                type="button" 
                onclick="togglePasswordField('{{ $name }}')" 
                class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
            >
                <i id="{{ $name }}_eye" class="fas fa-eye"></i>
            </button>
        </div>
    @else
        <input 
            id="{{ $name }}" 
            type="{{ $type }}" 
            name="{{ $name }}" 
            value="{{ old($name, $value) }}"
            {{ $required ? 'required' : '' }}
            {{ $attributes->merge(['class' => 'modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400']) }}
            placeholder="{{ $placeholder }}"
        >
    @endif
    
    @error($name)
        <p class="text-red-500 text-sm flex items-center">
            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
        </p>
    @enderror
</div>