<section class="panel live-tally-panel" data-live-tally data-tally-url="{{ route('teacher.tally', ['teacherKey' => $teacherKey]) }}">
    <div class="section-heading">
        <h2 data-tally-title>{{ $tally['status'] === 'closed' ? 'Perolehan suara akhir' : 'Perolehan suara sementara' }}</h2>
        <span class="badge badge-neutral" data-tally-badge>{{ ['preparation' => 'Belum dimulai', 'open' => 'Sementara · khusus guru', 'closed' => 'Hasil akhir'][$tally['status']] }}</span>
    </div>
    <p class="field-help">Perolehan suara diperbarui otomatis setiap 10 detik selama pemilihan berlangsung. Hasil sementara hanya untuk pemantauan guru; pengumuman kepada siswa tetap menunggu pemilihan ditutup dan aktivasi panitia.</p>
    <div class="live-tally-summary"><span>Total suara masuk</span><strong data-tally-total>{{ number_format($tally['total_votes'], 0, ',', '.') }}</strong></div>
    <p class="field-help" data-tally-message role="status">Data saat halaman dimuat.@if($tally['status'] === 'open') Diperbarui setiap 10 detik selama pemilihan berlangsung.@endif</p>
    <div class="table-scroll">
        <table class="results-table live-tally-table">
            <caption class="sr-only">Perolehan suara setiap calon, khusus untuk guru</caption>
            <thead><tr><th scope="col">Calon</th><th scope="col">Perolehan</th><th scope="col">Suara</th></tr></thead>
            <tbody data-tally-rows>
            @forelse($tally['candidates'] as $candidate)
                <tr><td><div class="tally-candidate"><small>Nomor {{ str_pad($candidate['number'], 2, '0', STR_PAD_LEFT) }}</small><strong>{{ $candidate['name'] }}</strong></div></td>
                    <td><progress value="{{ $candidate['percentage'] }}" max="100" aria-label="Perolehan {{ $candidate['name'] }}: {{ $candidate['votes'] }} suara">{{ $candidate['percentage'] }}%</progress><small>{{ number_format($candidate['percentage'], 1, ',', '.') }}%</small></td>
                    <td class="vote-count">{{ number_format($candidate['votes'], 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="3">Belum ada calon terdaftar. Tambahkan calon untuk melihat perolehan suara.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="field-help">Persentase dihitung dari total suara masuk. Pilihan individu siswa tetap rahasia.</p>
</section>
