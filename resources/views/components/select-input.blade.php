@props(['disabled' => false])

<select @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-pu-navy-500 focus:ring-pu-navy-500 rounded-md shadow-sm']) }}>
    {{ $slot }}
</select>
