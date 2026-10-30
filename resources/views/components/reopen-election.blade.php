<section class="panel control-panel reopen-panel" id="reopen-election">
    <h2>Buka kembali pemilihan</h2>
    <p>Lanjutkan pemilihan untuk siswa yang belum memilih. Seluruh suara lama tetap tersimpan dan siswa yang sudah memilih tidak dapat memilih lagi. Data siswa serta calon tetap dikunci.</p>
    <p>Pengumuman dan rekap hasil akan disembunyikan selama pemilihan berlangsung. Aktifkan kembali pengumuman setelah pemilihan ditutup lagi.</p>
    <form method="post" action="{{ route('teacher.reopen', ['teacherKey' => $teacherKey]) }}" data-submit-once>
        @csrf
        <label for="reopen-closes-at">Batas tutup baru (WIB, opsional)</label>
        <input id="reopen-closes-at" name="reopen_closes_at" type="datetime-local" value="{{ old('reopen_closes_at') }}" aria-describedby="reopen-time-help{{ $errors->has('reopen_closes_at') ? ' reopen-closes-at-error' : '' }}" @error('reopen_closes_at') aria-invalid="true" @enderror>
        <p class="field-help" id="reopen-time-help">Jadwal penutupan lama diganti. Kosongkan untuk menutup manual, atau isi waktu tutup baru setelah waktu server saat ini.</p>
        @include('components.field-error', ['name' => 'reopen_closes_at', 'id' => 'reopen-closes-at'])
        <label for="confirmation">Ketik BUKA KEMBALI untuk konfirmasi</label>
        <input id="confirmation" name="confirmation" type="text" required pattern="BUKA KEMBALI" autocomplete="off" placeholder="BUKA KEMBALI" @error('confirmation') aria-invalid="true" aria-describedby="confirmation-error" @enderror>
        @include('components.field-error', ['name' => 'confirmation'])
        <button type="submit" class="button button-primary">Buka kembali pemilihan <span aria-hidden="true">→</span></button>
    </form>
</section>
