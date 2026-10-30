<ol class="steps" aria-label="Tahapan pemilihan">
    @foreach([1 => 'Masukkan NIS', 2 => 'Pilih calon', 3 => 'Konfirmasi'] as $number => $label)
        <li class="{{ $step === $number ? 'current' : ($step > $number ? 'done' : '') }}" @if($step === $number) aria-current="step" @endif><span>{{ $step > $number ? '✓' : '0'.$number }}</span>{{ $label }}</li>
    @endforeach
</ol>
