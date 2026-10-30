<?php

return [
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'numeric' => ':attribute harus berupa angka.',
    'in' => ':attribute tidak sesuai. Ikuti petunjuk konfirmasi.',
    'regex' => 'Format :attribute tidak valid.',
    'unique' => ':attribute sudah digunakan.',
    'exists' => ':attribute tidak tersedia.',
    'image' => ':attribute harus berupa gambar.',
    'mimes' => 'Format :attribute tidak didukung.',
    'uploaded' => ':attribute gagal diunggah. Periksa ukuran berkas dan coba kembali.',
    'min' => ['numeric' => ':attribute minimal :min.', 'string' => ':attribute minimal :min karakter.'],
    'max' => ['numeric' => ':attribute maksimal :max.', 'string' => ':attribute maksimal :max karakter.', 'file' => ':attribute maksimal :max KB.'],
    'attributes' => [
        'nis' => 'NIS', 'name' => 'Nama', 'class_name' => 'Kelas', 'number' => 'Nomor urut',
        'vision' => 'Visi', 'mission' => 'Misi', 'photo' => 'Foto', 'confirmation' => 'Konfirmasi',
    ],
];
