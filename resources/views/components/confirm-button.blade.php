@props([
    'action',                 // route / url
    'method' => 'POST',       // POST, DELETE, PUT...
    'label' => 'Confirmar',   // texto del botón
    'variant' => 'primary',   // primary | success | danger | warning | neutral
    'swalTitle' => '¿Estás segura?',
    'swalText' => '',
    'swalIcon' => 'warning',  // success | error | warning | info | question
])

@php
    $classes = match($variant) {
        'success' => 'bg-emerald-700 hover:bg-emerald-800 text-white',
        'danger'  => 'bg-rose-700 hover:bg-rose-800 text-white',
        'warning' => 'bg-amber-600 hover:bg-amber-700 text-white',
        'neutral' => 'bg-gray-700 hover:bg-gray-600 text-gray-100',
        default   => 'bg-sky-700 hover:bg-sky-800 text-white',
    };
@endphp

<form method="POST" action="{{ $action }}" class="inline confirm-form">
    @csrf
    @if (!in_array(strtoupper($method), ['GET', 'POST']))
        @method($method)
    @endif

    <button type="button"
            class="px-3 py-1 rounded text-sm {{ $classes }} confirm-button"
            data-swal-title="{{ $swalTitle }}"
            data-swal-text="{{ $swalText }}"
            data-swal-icon="{{ $swalIcon }}">
        {{ $label }}
    </button>
</form>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.confirm-button').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const form = this.closest('form');
                        const title = this.dataset.swalTitle || '¿Confirmar acción?';
                        const text  = this.dataset.swalText  || '';
                        const icon  = this.dataset.swalIcon  || 'warning';

                        Swal.fire({
                            title: title,
                            text: text,
                            icon: icon,
                            showCancelButton: true,
                            confirmButtonText: 'Sí, continuar',
                            cancelButtonText: 'Cancelar',
                            background: '#020617',
                            color: '#e5e7eb',
                            confirmButtonColor: '#16a34a',
                            cancelButtonColor: '#64748b'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                form.submit();
                            }
                        });
                    });
                });
            });
        </script>
    @endpush
@endonce
