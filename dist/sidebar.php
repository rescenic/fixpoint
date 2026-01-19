<?php
include 'koneksi.php';
$user_id = $_SESSION['user_id'];

// Ambil semua file_menu yang boleh diakses user ini
$allowed_files = [];
$query = "SELECT menu.file_menu FROM akses_menu 
          JOIN menu ON akses_menu.menu_id = menu.id 
          WHERE akses_menu.user_id = '$user_id'";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
  $allowed_files[] = $row['file_menu'];
}
?>

<!-- sidebar.php -->
<style>
  /* Gaya untuk teks sidebar */
  .main-sidebar,
  .main-sidebar a,
  .main-sidebar .menu-header,
  .main-sidebar .nav-link,
  .main-sidebar span,
  .main-sidebar i {
    color: #000 !important;
  }

  /* Untuk memastikan ikon tidak kehilangan warna */
  .main-sidebar i {
    color: #000 !important;
  }

  /* Agar teks tombol footer tetap terbaca */
  .hide-sidebar-mini .btn {
    color: #fff !important;
  }
  
  /* Animasi untuk icon TTE */
  .tte-icon-active {
    animation: pulse 2s infinite;
  }
  
  @keyframes pulse {
    0%, 100% {
      opacity: 1;
      color: #28a745 !important;
    }
    50% {
      opacity: 0.7;
      color: #20c997 !important;
    }
  }
</style>

<div class="main-sidebar sidebar-style-2">
  <aside id="sidebar-wrapper">
    <div class="sidebar-brand">
      <a href="dashboard.php">F.I.X.P.O.I.N.T</a>
    </div>
    <div class="sidebar-brand sidebar-brand-sm">
      <a href="dashboard.php">FP</a>
    </div>

   <div class="p-3">
  <input type="text" class="form-control form-control-sm" id="searchMenu" placeholder="Cari menu...">
</div>

<ul class="sidebar-menu" id="menuList">
  <!-- DASHBOARD -->
  <li class="menu-header">DASHBOARD</li>
  <li class="dropdown">
    <a href="#" class="nav-link has-dropdown">
      <i class="fas fa-fire"></i> <span>DASHBOARD</span>
    </a>
    <ul class="dropdown-menu">
      <?php if (in_array('dashboard.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="dashboard.php">
          <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
      </li>
      <?php endif; ?>

      <?php if (in_array('dashboard2.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="dashboard2.php">
          <i class="fas fa-tachometer-alt"></i> <span>Dashboard Direktur</span>
        </a>
      </li>
      <?php endif; ?>
    </ul>
  </li>

 

<li class="menu-header">
  <i class="fa fa-list"></i> Pengajuan / Order
</li>

<li class="dropdown">
  <a href="#" class="nav-link has-dropdown">
    <i class="fa fa-cog"></i>
    <span>PENGAJUAN</span>
  </a>

  <ul class="dropdown-menu">

    <?php if (in_array('order_tiket_it_software.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="order_tiket_it_software.php">
        <i class="fa fa-code"></i>
        <span>Tiket IT Software</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('order_tiket_it_hardware.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="order_tiket_it_hardware.php">
        <i class="fa fa-desktop"></i>
        <span>Tiket IT Hardware</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('order_tiket_sarpras.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="order_tiket_sarpras.php">
        <i class="fa fa-wrench"></i>
        <span>Tiket Sarpras</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('off_duty.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="off_duty.php">
        <i class="fa fa-user"></i>
        <span>Off-Duty</span>
      </a>
    </li>
    <?php endif; ?>

   <?php if (in_array('izin_keluar.php', $allowed_files)): ?>
<li>
  <a class="nav-link" href="izin_keluar.php">
    <i class="fa fa-share"></i>
    <span>Izin Keluar</span>
  </a>
</li>
<?php endif; ?>


    <?php if (in_array('pengajuan_cuti.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="pengajuan_cuti.php">
        <i class="fa fa-calendar"></i>
        <span>Pengajuan Cuti</span>
      </a>
    </li>
    <?php endif; ?>

<?php if (in_array('ganti_jadwal_dinas.php', $allowed_files)): ?>
<li>
  <a class="nav-link" href="ganti_jadwal_dinas.php">
    <i class="fa fa-retweet"></i>
    <span>Ganti Jadwal</span>
  </a>
</li>
<?php endif; ?>


<?php if (in_array('edit_data_simrs.php', $allowed_files)): ?>
<li>
  <a class="nav-link" href="edit_data_simrs.php">
    <i class="fa fa-edit"></i>
    <span>Edit Data SIMRS</span>
  </a>
</li>
<?php endif; ?>


    <?php if (in_array('hapus_data.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="hapus_data.php">
        <i class="fa fa-trash"></i>
        <span>Hapus Data SIMRS</span>
      </a>
    </li>
    <?php endif; ?>

  </ul>
</li>


<li class="menu-header">
  <i class="fa fa-folder-open"></i> Data Pengajuan
</li>

<li class="dropdown">
  <a href="#" class="nav-link has-dropdown">
    <i class="fa fa-database"></i>
    <span>DATA PENGAJUAN</span>
  </a>

  <ul class="dropdown-menu">

    <?php if (in_array('data_tiket_it_software.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_tiket_it_software.php">
        <i class="fa fa-code"></i>
        <span>Data Tiket IT Soft</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('data_tiket_it_hardware.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_tiket_it_hardware.php">
        <i class="fa fa-desktop"></i>
        <span>Data Tiket IT Hard</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('data_tiket_sarpras.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_tiket_sarpras.php">
        <i class="fa fa-wrench"></i>
        <span>Data Tiket Sarpras</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('data_off_duty.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_off_duty.php">
        <i class="fa fa-calendar"></i>
        <span>Data Off-Duty</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('acc_edit_data.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="acc_edit_data.php">
        <i class="fa fa-check-circle"></i>
        <span>Permintaan Edit</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('data_permintaan_hapus_data_simrs.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_permintaan_hapus_data_simrs.php">
        <i class="fa fa-trash"></i>
        <span>Hapus Data</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('data_cuti_delegasi.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_cuti_delegasi.php">
        <i class="fa fa-users"></i>
        <span>ACC Cuti Delegasi</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('data_cuti_atasan.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_cuti_atasan.php">
        <i class="fa fa-user"></i>
        <span>ACC Cuti Atasan</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('data_cuti_hrd.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_cuti_hrd.php">
        <i class="fa fa-id-badge"></i>
        <span>ACC Cuti HRD</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('acc_keluar_atasan.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="acc_keluar_atasan.php">
        <i class="fa fa-user"></i>
        <span>ACC Keluar Atasan</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('acc_keluar_sdm.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="acc_keluar_sdm.php">
        <i class="fa fa-check"></i>
        <span>ACC Keluar SDM</span>
      </a>
    </li>
    <?php endif; ?>

  </ul>
</li>


<!-- TTE -->
<li class="menu-header">Tanda Tangan Elektronik</li>

<?php
// Cek status lisensi TTE (simple & safe)
$tte_active = false;

try {
    $perusahaan_result = $conn->query("SELECT id FROM perusahaan LIMIT 1");
    if ($perusahaan_result) {
        $perusahaan_row = $perusahaan_result->fetch_assoc();
        if ($perusahaan_row) {
            $pid = $perusahaan_row['id'];
            $tte_result = $conn->query("SELECT id FROM tte_licenses WHERE perusahaan_id = $pid AND status = 'active' LIMIT 1");
            if ($tte_result && $tte_result->num_rows > 0) {
                $tte_active = true;
            }
        }
    }
} catch (Exception $e) {
    $tte_active = false;
}
?>

<?php if ($tte_active): ?>
    <li class="dropdown">
      <a href="#" class="nav-link has-dropdown">
        <i class="fas fa-lightbulb tte-icon-active" style="color: #28a745 !important;"></i>
        <span>TTE</span>
        <span class="badge badge-success ml-2">Active</span>
      </a>
      <ul class="dropdown-menu">

        <?php if (in_array('cek_tte.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="cek_tte.php">
            <i class="fas fa-search"></i>
            <span>Cek TTE</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array('bubuhkan_tte.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="bubuhkan_tte.php">
            <i class="fas fa-file-signature"></i>
            <span>TTE Dokumen</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array('dokumen_tte.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="dokumen_tte.php">
            <i class="fas fa-folder-open"></i>
            <span>Dokumen Saya</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array('dokumen_tte_semua.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="dokumen_tte_semua.php">
            <i class="fas fa-archive"></i>
            <span>Semua Dok. TTE</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array('buat_tte.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="buat_tte.php">
            <i class="fas fa-qrcode"></i>
            <span>TTE Generate</span>
          </a>
        </li>
        <?php endif; ?>

       <?php if (in_array('bubuhkan_stampel.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="bubuhkan_stampel.php">
            <i class="fas fa-stamp"></i>
            <span>Stempel Dokumen</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array('cek_stampel.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="cek_stampel.php">
            <i class="fas fa-certificate"></i>
            <span>Cek E-Stemp</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array('buat_stampel.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="buat_stampel.php">
            <i class="fas fa-file-signature"></i>
            <span>Generate E-Stemp</span>
          </a>
        </li>
        <?php endif; ?>


      </ul>
    </li>

<?php else: ?>
    <li class="dropdown">
      <a href="#" class="nav-link has-dropdown">
        <i class="fas fa-lock text-warning"></i>
        <span>TTE</span>
        <span class="badge badge-warning ml-2">Locked</span>
      </a>
      <ul class="dropdown-menu">
        <li>
          <a class="nav-link" href="#" data-toggle="modal" data-target="#modalAktivasiTTE">
            <i class="fas fa-key"></i>
            <span>Aktivasi TTE</span>
          </a>
        </li>
      </ul>
    </li>
<?php endif; ?>


<li class="menu-header">BUAT DOKUMEN / SURAT</li>

<li class="dropdown">
  <a href="#" class="nav-link has-dropdown">
    <i class="fas fa-file-signature"></i>
    <span>BUAT DOKUMEN</span>
  </a>

  <ul class="dropdown-menu">

    <?php if (in_array('spo.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="spo.php">
        <i class="fas fa-file-medical"></i>
        <span>SPO</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('rapat_bulanan.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="rapat_bulanan.php">
        <i class="fas fa-calendar-check"></i>
        <span>Rapat Bulanan</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('surat_edaran.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="surat_edaran.php">
        <i class="fas fa-bullhorn"></i>
        <span>Surat Edaran</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('pemberitahuan.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="pemberitahuan.php">
        <i class="fas fa-bell"></i>
        <span>Pemberitahuan</span>
      </a>
    </li>
    <?php endif; ?>



       <?php if (in_array('master_no_surat.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="master_no_surat.php">
        <i class="fas fa-chalkboard-teacher"></i>
        <span>Laporan Surat/SPO</span>
      </a>
    </li>
    <?php endif; ?>

  </ul>
</li>



<li class="menu-header">IT DEPARTEMEN</li>
<li class="dropdown">
  <a href="#" class="nav-link has-dropdown"><i class="fas fa-desktop" style="color:#007bff;"></i> <span>IT DEPARTEMEN</span></a>
  <ul class="dropdown-menu">

  

   <?php if (in_array('data_permintaan_hapus_simrs.php', $allowed_files)): ?>
  <li>
    <a class="nav-link" href="data_permintaan_hapus_simrs.php">
      <i class="fas fa-calendar-times" style="color:#ffc107;"></i> <span>Data Hapus SIMRS</span>
    </a>
  </li>
  <?php endif; ?>

     <?php if (in_array('data_permintaan_edit_simrs.php', $allowed_files)): ?>
  <li>
    <a class="nav-link" href="data_permintaan_edit_simrs.php">
      <i class="fas fa-calendar-times" style="color:#ffc107;"></i> <span>Data Edit SIMRS</span>
    </a>
  </li>
  <?php endif; ?>

  <?php if (in_array('handling_time.php', $allowed_files)): ?>
  <li>
    <a class="nav-link" href="handling_time.php">
      <i class="fas fa-stopwatch" style="color:#e83e8c;"></i> <span>Handling Time</span>
    </a>
  </li>
  <?php endif; ?>

  <?php if (in_array('spo_it.php', $allowed_files)): ?>
  <li>
    <a class="nav-link" href="spo_it.php">
      <i class="fas fa-file-alt" style="color:#007bff;"></i> <span>SPO IT</span>
    </a>
  </li>
  <?php endif; ?>

  <?php if (in_array('input_spo_it.php', $allowed_files)): ?>
  <li>
    <a class="nav-link" href="input_spo_it.php">
      <i class="fas fa-file-signature" style="color:#28a745;"></i> <span>Input SPO IT</span>
    </a>
  </li>
  <?php endif; ?>

  <?php if (in_array('berita_acara_it.php', $allowed_files)): ?>
  <li>
    <a class="nav-link" href="berita_acara_it.php">
      <i class="fas fa-scroll" style="color:#fd7e14;"></i> <span>Berita Acara</span>
    </a>
  </li>
  <?php endif; ?>

  <?php if (in_array('data_barang_it.php', $allowed_files)): ?>
  <li>
    <a class="nav-link" href="data_barang_it.php">
      <i class="fas fa-boxes" style="color:#6c757d;"></i> <span>Data Barang IT</span>
    </a>
  </li>
  <?php endif; ?>

  <?php if (in_array('maintenance_rutin.php', $allowed_files)): ?>
  <li>
    <a class="nav-link" href="maintenance_rutin.php">
      <i class="fas fa-sync-alt" style="color:#6610f2;"></i> <span>Maintenance Rutin</span>
    </a>
  </li>
  <?php endif; ?>

  <?php if (in_array('koneksi_bridging.php', $allowed_files)): ?>
  <li>
    <a class="nav-link" href="koneksi_bridging.php">
      <i class="fas fa-link" style="color:#20c997;"></i> <span>Koneksi Bridging</span>
    </a>
  </li>
  <?php endif; ?>

<?php if (in_array('log_login.php', $allowed_files)): ?>
  <li>
    <a class="nav-link" href="log_login.php">
      <i class="fas fa-calendar-times" style="color:#ffc107;"></i> <span>Log Login</span>
    </a>
  </li>
  <?php endif; ?>

  </ul>
</li>


<li class="menu-header">SARPRAS</li>
<li class="dropdown">
  <a href="#" class="nav-link has-dropdown">
    <i class="fa fa-wrench"></i> <span>SARPRAS</span>
  </a>

  <ul class="dropdown-menu">

    <?php if (in_array('handling_time_sarpras.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="handling_time_sarpras.php">
        <i class="fas fa-stopwatch"></i> <span>Handling Time</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('data_barang_ac.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_barang_ac.php">
        <i class="fas fa-boxes"></i> <span>Barang Sarpras</span>
      </a>
    </li>
    <?php endif; ?>

  <?php if (in_array('maintanance_rutin_sarpras.php', $allowed_files)): ?>
<li>
  <a class="nav-link" href="maintanance_rutin_sarpras.php">
    <i class="fas fa-cogs"></i> <span>Maintenance</span>
  </a>
</li>
<?php endif; ?>

  </ul>
</li>


<li class="menu-header">KEUANGAN</li>
<li class="dropdown">
  <a href="#" class="nav-link has-dropdown">
    <i class="fas fa-wallet"></i> <span>KEUANGAN</span>
  </a>
  <ul class="dropdown-menu">

    <!-- Transaksi Gaji -->
      <?php if (in_array('input_gaji.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="input_gaji.php">
            <i class="fas fa-user-clock"></i> <span>Transaksi Gaji</span>
          </a>
        </li>
        <?php endif; ?>

    <!-- Data Gaji -->
    <?php if (in_array('data_gaji.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="data_gaji.php">
            <i class="fas fa-user-clock"></i> <span>Data Gaji</span>
          </a>
        </li>
        <?php endif; ?>

    <!-- Submenu Penerimaan -->
    <li class="dropdown">
      <a href="#" class="nav-link has-dropdown">
        <i class="fas fa-arrow-down"></i> <span>Penerimaan</span>
      </a>
      <ul class="dropdown-menu">
        <?php if (in_array('masa_kerja.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="masa_kerja.php">
            <i class="fas fa-user-clock"></i> <span>Masa Kerja</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array('kesehatan.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="kesehatan.php">
            <i class="fas fa-heartbeat"></i> <span>Kesehatan</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array('fungsional.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="fungsional.php">
            <i class="fas fa-user-tag"></i> <span>Fungsional</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array('struktural.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="struktural.php">
            <i class="fas fa-sitemap"></i> <span>Struktural</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array('gaji_pokok.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="gaji_pokok.php">
            <i class="fas fa-coins"></i> <span>Gaji Pokok</span>
          </a>
        </li>
        <?php endif; ?>
      </ul>
    </li>

    <!-- Submenu Potongan -->
    <li class="dropdown">
      <a href="#" class="nav-link has-dropdown">
        <i class="fas fa-arrow-up"></i> <span>Potongan</span>
      </a>
      <ul class="dropdown-menu">

        <?php if (in_array('potongan_bpjs_kes.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="potongan_bpjs_kes.php">
        <i class="fas fa-heartbeat"></i> <span>BPJS Kesehatan</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('potongan_bpjs_jht.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="potongan_bpjs_jht.php">
        <i class="fas fa-hand-holding-usd"></i> <span>BPJS TK JHT</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('potongan_bpjs_tk_jp.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="potongan_bpjs_tk_jp.php">
        <i class="fas fa-briefcase-medical"></i> <span>BPJS TK JP</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('potongan_dana_sosial.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="potongan_dana_sosial.php">
        <i class="fas fa-donate"></i> <span>Dana Sosial</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('pph21.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="pph21.php">
        <i class="fas fa-receipt"></i> <span>PPH21</span>
      </a>
    </li>
    <?php endif; ?>
        
      </ul>
    </li>

  </ul>
</li>

<li class="menu-header">HR / SDM</li>
<li class="dropdown">
  <a href="#" class="nav-link has-dropdown">
    <i class="fas fa-users-cog"></i> <span>HR / SDM</span>
  </a>
  <ul class="dropdown-menu">

    <?php if (in_array('data_cuti.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_cuti.php">
        <i class="fas fa-database"></i> <span>Data Cuti</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('data_izin_keluar.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_izin_keluar.php">
        <i class="fas fa-clipboard-check"></i> <span>Data Izin Keluar</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('rekap_catatan_kerja.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="rekap_catatan_kerja.php">
        <i class="fas fa-clipboard-list"></i> <span>Rekap Kerja</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('master_cuti.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="master_cuti.php">
        <i class="fas fa-calendar-alt"></i> <span>Master Cuti</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('jatah_cuti.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="jatah_cuti.php">
        <i class="fas fa-calendar-check"></i> <span>Jatah Cuti</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('data_karyawan.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_karyawan.php">
        <i class="fas fa-id-badge"></i> <span>Data Karyawan</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('exit_clearance.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="exit_clearance.php">
        <i class="fas fa-sign-out-alt"></i>
        <span>Exit Clearance</span>
      </a>
    </li>
    <?php endif; ?>

  </ul>
</li>



<!-- KESEKTARIATAN -->
<li class="menu-header">KESEKTARIATAN</li>
<li class="dropdown">
  <a href="#" class="nav-link has-dropdown">
    <i class="fas fa-building"></i> <span>KESEKTARIATAN</span>
  </a>
  <ul class="dropdown-menu">

    <?php if (in_array('surat_masuk.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="surat_masuk.php">
        <i class="fas fa-envelope-open-text"></i> <span>Surat Masuk</span>
      </a>
    </li>
    <?php endif; ?>

       <?php if (in_array('disposisi.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="disposisi.php">
        <i class="fas fa-envelope-open-text"></i> <span>Disposisi Surat</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('surat_keluar.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="surat_keluar.php">
        <i class="fas fa-paper-plane"></i> <span>Surat Keluar</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('arsip_digital.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="arsip_digital.php">
        <i class="fas fa-archive"></i> <span>Arsip Digital</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('agenda_direktur.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="agenda_direktur.php">
        <i class="fas fa-calendar-alt"></i> <span>Agenda Direktur</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('lihat_agenda.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="lihat_agenda.php">
        <i class="fas fa-calendar-check"></i> <span>Lihat Agenda</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('kategori_arsip.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="kategori_arsip.php">
        <i class="fas fa-folder-open"></i> <span>Kategori Arsip</span>
      </a>
    </li>
    <?php endif; ?>

  </ul>
</li>


<li class="menu-header">LAPORAN KERJA</li>
<li class="dropdown">
  <a href="#" class="nav-link has-dropdown">
    <i class="fas fa-clipboard-list"></i> <span>LAPORAN KERJA</span>
  </a>
  <ul class="dropdown-menu">

      <?php if (in_array('catatan_kerja.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="catatan_kerja.php">
        <i class="fas fa-users"></i> <span>Catatan Kerja</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('laporan_harian.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="laporan_harian.php">
        <i class="fas fa-calendar-check"></i> <span>Laporan Harian</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('laporan_bulanan.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="laporan_bulanan.php">
        <i class="fas fa-file-alt"></i><span>Laporan Bulanan</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('laporan_tahunan.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="laporan_tahunan.php">
        <i class="fas fa-calendar-alt"></i> <span>Laporan Tahunan</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('data_laporan_harian.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_laporan_harian.php">
        <i class="fas fa-file-alt"></i> <span>Data Lap Harian</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('data_laporan_bulanan.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_laporan_bulanan.php">
        <i class="fas fa-file-invoice"></i> <span>Data Lap Bulanan</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('data_laporan_tahunan.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_laporan_tahunan.php">
        <i class="fas fa-file-archive"></i> <span>Data Lap Tahunan</span>
      </a>
    </li>
    <?php endif; ?>

         <?php if (in_array('input_kpi.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="input_kpi.php">
        <i class="fas fa-file-archive"></i> <span>Inpu KPI</span>
      </a>
    </li>
    <?php endif; ?>

      <?php if (in_array('master_kpi.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="master_kpi.php">
        <i class="fas fa-file-archive"></i> <span>Master KPI</span>
      </a>
    </li>
    <?php endif; ?>

  </ul>
</li>

<li class="menu-header">AKREDITASI</li>
<li class="dropdown">
  <a href="#" class="nav-link has-dropdown">
    <i class="fas fa-folder-open"></i> <span>FILE AKREDITASI</span>
  </a>
  <ul class="dropdown-menu">

    <?php if (in_array('data_dokumen.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_dokumen.php">
        <i class="fas fa-file-alt"></i> <span>Data Dokumen</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('input_dokumen.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="input_dokumen.php">
        <i class="fas fa-plus"></i> <span>Input Dokumen</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('master_pokja.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="master_pokja.php">
        <i class="fas fa-database"></i> <span>Master Pokja</span>
      </a>
    </li>
    <?php endif; ?>

  </ul>
</li>

<!-- KOMITE KEPERAWATAN -->
<li class="menu-header">KOMITE KEPERAWATAN</li>
<li class="dropdown">
  <a href="#" class="nav-link has-dropdown">
    <i class="fas fa-user-md"></i> <span>KOMKEP</span>
  </a>
  <ul class="dropdown-menu">

     <?php if (in_array('kredensial.php', $allowed_files)): ?>
    <li class="dropdown">
      <a href="#" class="nav-link has-dropdown">
        <i class="fas fa-chart-bar"></i> <span>Kredensial</span>
      </a>
      <ul class="dropdown-menu">

        <?php if (in_array('praktek.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="praktek.php">
            <i class="fas fa-briefcase-medical"></i> <span>Praktek</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array('wawancara.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="wawancara.php">
            <i class="fas fa-comments"></i> <span>Wawancara</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array('ujian_tertulis.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="ujian_tertulis.php">
            <i class="fas fa-edit"></i> <span>Tertulis</span>
          </a>
        </li>
        <?php endif; ?>

      </ul>
    </li>
    <?php endif; ?>


   
  <?php if (in_array('hasil_kredensial.php', $allowed_files)): ?>
    <li class="dropdown">
      <a href="#" class="nav-link has-dropdown">
        <i class="fas fa-chart-bar"></i> <span>Hasil Kredensial</span>
      </a>
      <ul class="dropdown-menu">

        <?php if (in_array('hasil_praktek.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="hasil_praktek.php">
            <i class="fas fa-briefcase-medical"></i> <span>Praktek</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array('hasil_wawancara.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="hasil_wawancara.php">
            <i class="fas fa-comments"></i> <span>Wawancara</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array('hasil_ujian.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="hasil_ujian.php">
            <i class="fas fa-edit"></i> <span>Tertulis</span>
          </a>
        </li>
        <?php endif; ?>

      </ul>
    </li>
    <?php endif; ?>


    <?php if (in_array('kegiatan_komite.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="kegiatan_komite.php">
        <i class="fas fa-calendar-alt"></i> <span>Kegiatan Komite</span>
      </a>
    </li>
    <?php endif; ?>

   

    <?php if (in_array('laporan_komite.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="laporan_komite.php">
        <i class="fas fa-file-alt"></i> <span>Laporan Komite</span>
      </a>
    </li>
    <?php endif; ?>


       <?php if (in_array('judul.php', $allowed_files)): ?>
    <li class="dropdown">
      <a href="#" class="nav-link has-dropdown">
        <i class="fas fa-chart-bar"></i> <span>Master Data</span>
      </a>
      <ul class="dropdown-menu">

        <?php if (in_array('judul_soal.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="judul_soal.php">
            <i class="fas fa-briefcase-medical"></i> <span>Judul Soal</span>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array('input_soal.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="input_soal.php">
            <i class="fas fa-comments"></i> <span>Input Soal</span>
          </a>
        </li>
        <?php endif; ?>


          <?php if (in_array('master_komponen_praktek.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="master_komponen_praktek.php">
            <i class="fas fa-comments"></i> <span>Komponen Ujian Praktek</span>
          </a>
        </li>
        <?php endif; ?>

           <?php if (in_array('data_anggota_komite.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="data_anggota_komite.php">
            <i class="fas fa-comments"></i> <span>Anggota Komite</span>
          </a>
        </li>
        <?php endif; ?>

    <?php if (in_array('jenis_kredensial.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="jenis_kredensial.php">
            <i class="fas fa-comments"></i> <span>Jenis Kredensial</span>
          </a>
        </li>
        <?php endif; ?>

         <?php if (in_array('jabatan_komite.php', $allowed_files)): ?>
        <li>
          <a class="nav-link" href="jabatan_komite.php">
            <i class="fas fa-comments"></i> <span>Jabatan Komite</span>
          </a>
        </li>
        <?php endif; ?>
    
      </ul>
    </li>
    <?php endif; ?>


  </ul>
</li>


  


<!-- INDIKATOR MUTU -->
<li class="menu-header">INDIKATOR MUTU</li>
<li class="dropdown">
  <a href="#" class="nav-link has-dropdown">
    <i class="fas fa-chart-line"></i> <span>INDIKATOR MUTU</span>
  </a>
  <ul class="dropdown-menu">

    <?php if (in_array('master_indikator.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="master_indikator.php">
        <i class="fas fa-globe"></i> <span>Master IMN</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('master_indikator_rs.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="master_indikator_rs.php">
        <i class="fas fa-hospital"></i> <span>Master IMUT RS</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('master_indikator_unit.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="master_indikator_unit.php">
        <i class="fas fa-building"></i> <span>Master IMUT Unit</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('input_harian.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="input_harian.php">
        <i class="fas fa-keyboard"></i> <span>Input Imut Harian</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('capaian_imut.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="capaian_imut.php">
        <i class="fas fa-chart-bar"></i> <span>Capaian Imut</span>
      </a>
    </li>
    <?php endif; ?>

  </ul>
</li>

<!-- SIMRS -->
<li class="menu-header">LAPORAN SIMRS</li>
<li class="dropdown">
  <a href="#" class="nav-link has-dropdown">
    <i class="fas fa-hospital"></i> <span>LAPORAN SIMRS</span>
  </a>
  <ul class="dropdown-menu">

    <?php if (in_array('semua_antrian.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="semua_antrian.php">
        <i class="fas fa-chart-line"></i> <span>% Semua Antrian</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('mjkn_antrian.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="mjkn_antrian.php">
        <i class="fas fa-mobile-alt"></i> <span>% Antrian JKN</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('poli_antrian.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="poli_antrian.php">
        <i class="fas fa-user-md"></i> <span>% Antrian Poli</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('erm.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="erm.php">
        <i class="fas fa-file-medical"></i> <span>E-RM</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('satu_sehat.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="satu_sehat.php">
        <i class="fas fa-heartbeat"></i> <span>Satu Sehat</span>
      </a>
    </li>
    <?php endif; ?>

      <?php if (in_array('progres_kerja.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="progres_kerja.php">
        <i class="fas fa-heartbeat"></i> <span>Progres Kerja</span>
      </a>
    </li>
    <?php endif; ?>

       <?php if (in_array('slide_pelaporan.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="slide_pelaporan.php">
        <i class="fas fa-heartbeat"></i> <span>Slide Laporan</span>
      </a>
    </li>
    <?php endif; ?>

  </ul>
</li>


<li class="menu-header">JADWAL</li>
<li class="dropdown">
  <a href="#" class="nav-link has-dropdown"><i class="fas fa-clock"></i> <span>JADWAL</span></a>
  <ul class="dropdown-menu">

        <?php if (in_array('absensi.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="absensi.php">
        <i class="fas fa-user-check"></i> <span>Absensi</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('data_absensi.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_absensi.php">
        <i class="fas fa-user-check"></i> <span>Data Absensi</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('jadwal_dinas.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="jadwal_dinas.php">
        <i class="fas fa-calendar-plus"></i> <span>Input Jadwal</span>
      </a>
    </li>
    <?php endif; ?>



    <?php if (in_array('data_jadwal.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="data_jadwal.php">
        <i class="fas fa-calendar-alt"></i> <span>Data Jadwal</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('jam_kerja.php', $allowed_files)): ?>
    <li>
      <a class="nav-link" href="jam_kerja.php">
        <i class="fas fa-business-time"></i> <span>Jam Kerja</span>
      </a>
    </li>
    <?php endif; ?>

  </ul>
</li>




<!-- MASTER DATA -->
<li class="menu-header">MASTER DATA</li>
<li class="dropdown">
  <a href="#" class="nav-link has-dropdown"><i class="fas fa-folder"></i> <span>MASTER DATA</span></a>
  <ul class="dropdown-menu">

    <?php if (in_array('perusahaan.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="perusahaan.php">
          <i class="fas fa-building"></i> <span>Perusahaan</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('pengguna.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="pengguna.php">
          <i class="fas fa-user-cog"></i> <span>Pengguna</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('hak_akses.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="hak_akses.php">
          <i class="fas fa-user-shield"></i> <span>Hak Akses</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('master_url.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="master_url.php">
          <i class="fas fa-link"></i> <span>Master URL</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('mail_setting.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="mail_setting.php">
          <i class="fas fa-envelope"></i> <span>Mail Settings</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('tele_setting.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="tele_setting.php">
          <i class="fab fa-telegram"></i> <span>Telegram Settings</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('wa_setting.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="wa_setting.php">
          <i class="fab fa-whatsapp"></i> <span>Whatsapp Settings</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('kategori_hardware.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="kategori_hardware.php">
          <i class="fas fa-microchip"></i> <span>Kategori Hardware</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('kategori_software.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="kategori_software.php">
          <i class="fas fa-laptop-code"></i> <span>Kategori Software</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('unit_kerja.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="unit_kerja.php">
          <i class="fas fa-sitemap"></i> <span>Unit Kerja</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('jabatan.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="jabatan.php">
          <i class="fas fa-briefcase"></i> <span>Jabatan</span>
        </a>
      </li>
    <?php endif; ?>

     <?php if (in_array('master_poliklinik.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="master_poliklinik.php">
          <i class="fas fa-briefcase"></i> <span>Poliklinik</span>
        </a>
      </li>
    <?php endif; ?>

     <?php if (in_array('master_status_karyawan.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="master_status_karyawan.php">
          <i class="fas fa-briefcase"></i> <span>Status Karyawan</span>
        </a>
      </li>
    <?php endif; ?>

  </ul>
</li>

<!-- SETTING -->
<li class="menu-header">SETTING</li>
<li class="dropdown">
  <a href="#" class="nav-link has-dropdown"><i class="fas fa-cogs"></i> <span>SETTING</span></a>
  <ul class="dropdown-menu">

    <?php if (in_array('profile.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="profile.php">
          <i class="fas fa-user-circle"></i> <span>Akun Saya</span>
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('profile2.php', $allowed_files)): ?>
      <li>
        <a class="nav-link" href="profile2.php">
          <i class="fas fa-key"></i> <span>Profil Saya</span>
        </a>
      </li>
    <?php endif; ?>

  </ul>
</li>

    </ul>

   <!-- Footer Button -->
<div class="mt-4 mb-4 p-3 hide-sidebar-mini">
  <div class="row no-gutters text-center">

    <!-- BIO -->
    <div class="col-6 pr-1">
      <a href="#"
         class="btn btn-info btn-lg btn-block"
         data-toggle="modal"
         data-target="#tentangModal"
         title="Profil / Tentang Aplikasi">
        <i class="fa fa-user"></i> BIO
      </a>
    </div>

    <!-- TTE -->
    <div class="col-6 pl-1">
      <a href="#"
         class="btn btn-success btn-lg btn-block"
         data-toggle="modal"
         data-target="#tentangModalTTE"
         title="Tanda Tangan Elektronik">
        <i class="fa fa-qrcode"></i> TTE
      </a>
    </div>

  </div>
</div>

  </aside>
</div>

<!-- MODAL TENTANG APLIKASI -->
<style>
  .text-justify {
    text-align: justify;
  }
</style>

<div class="modal fade" id="tentangModal" tabindex="-1" role="dialog" aria-labelledby="tentangModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="tentangModalLabel">
          <i class="fas fa-info-circle mr-2"></i> Tentang Aplikasi
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p class="text-justify">
          Aplikasi (FixPoint) ini dikembangkan untuk mendukung efektivitas kerja dan transparansi layanan Manajemen di berbagai instansi.
          Aplikasi ini dapat digunakan secara bebas tanpa dipungut biaya dalam bentuk apa pun.
        </p>
        <p class="text-justify">
          Pengguna <strong>dilarang memperjualbelikan, menggandakan</strong> untuk tujuan komersial, atau memodifikasi aplikasi ini
          untuk keuntungan pribadi tanpa izin tertulis dari pengembang. Sangat di larang untuk menghapus/mengganti tentang aplikasi dan menghapus logo bawaan aplikasi.
        </p>
        <p class="text-justify">
          Apabila Anda merasa terbantu dan ingin mendukung pengembangan aplikasi ini ke depannya, donasi Kopi untuk ngodingnya
          dapat disalurkan melalui:
        </p>
        <ul class="text-justify">
          <li><strong>Rekening :</strong> BSI – <code>7134197557</code></li>
          <li><strong>Atas Nama :</strong> M. Wira Satria Buana</li>
          <li><strong>Instansi :</strong> RS. Permata Hati Muara Bungo - Jambi</li>
          <li><strong>No. Tlp :</strong> 0821 7784 6209</li>
        </ul>
        <p class="text-justify">
          Setiap bentuk dukungan akan sangat berarti untuk pengembangan fitur lebih lanjut dan pemeliharaan sistem.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL TENTANG TTE -->
<div class="modal fade" id="tentangModalTTE" tabindex="-1" role="dialog" aria-labelledby="tentangModalTTELabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="tentangModalTTELabel">
          <i class="fas fa-info-circle mr-2"></i> Tentang TTE FixPoint
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
   <div class="modal-body">

  <p class="text-justify">
    <strong>FixPoint</strong> merupakan aplikasi internal yang memiliki fitur
    <strong>Tanda Tangan Elektronik (TTE) mandiri</strong> untuk mendukung
    proses administrasi dan dokumentasi di lingkungan rumah sakit.
    TTE yang digunakan pada aplikasi FixPoint adalah
    <strong>Tanda Tangan Elektronik Non-Sertifikasi</strong>
    yang diperuntukkan khusus untuk kebutuhan <strong>internal</strong>.
  </p>

  <p class="text-justify">
    TTE Non-Sertifikasi dalam FixPoint dibuat dan dikelola langsung oleh sistem,
    tanpa melibatkan Penyelenggara Sertifikasi Elektronik (PSrE).
    Setiap pengguna memiliki <strong>TTE pribadi</strong> yang
    dihasilkan melalui menu <strong>TTE → Generate</strong>,
    dan hanya dapat digunakan oleh pemilik akun yang bersangkutan.
  </p>

  <p class="text-justify">
    Proses pembubuhan TTE <strong>wajib dilakukan melalui aplikasi FixPoint</strong>
    pada menu <strong>TTE → Dokumen</strong>.
    TTE yang telah dibubuhkan bersifat <strong>unik</strong>,
    <strong>tidak dapat diduplikasi</strong>,
    serta <strong>tidak dapat dimanipulasi</strong> pada dokumen lain.
    Apabila TTE ditempelkan ulang atau dipindahkan ke dokumen berbeda,
    maka hasil verifikasi akan dinyatakan <strong>tidak valid</strong>.
  </p>

  <p class="text-justify">
    Untuk memastikan keaslian dan keabsahan TTE,
    proses verifikasi hanya dapat dilakukan melalui aplikasi FixPoint
    pada menu <strong>TTE → Cek TTE</strong>.
    Sistem akan melakukan pemeriksaan keutuhan dokumen,
    identitas penanda tangan, serta riwayat pembubuhan TTE.
    <strong>TTE dinyatakan sah apabila dibubuhkan melalui sistem FixPoint</strong>.
  </p>

  <hr>

  <p class="text-justify">
    <strong>Dasar Hukum Penggunaan TTE Non-Sertifikasi:</strong>
  </p>

  <ul class="text-justify">
    <li>
      <strong>Undang-Undang Nomor 11 Tahun 2008</strong> tentang Informasi dan Transaksi Elektronik
      sebagaimana telah diubah dengan <strong>UU Nomor 19 Tahun 2016</strong>,
      menyatakan bahwa Tanda Tangan Elektronik memiliki kekuatan hukum dan akibat hukum yang sah.
    </li>
    <li>
      <strong>Peraturan Pemerintah Nomor 71 Tahun 2019</strong> tentang Penyelenggaraan Sistem
      dan Transaksi Elektronik (PP PSTE) membagi TTE menjadi
      <strong>TTE Tersertifikasi</strong> dan <strong>TTE Tidak Tersertifikasi</strong>.
    </li>
    <li>
      TTE Tidak Tersertifikasi <strong>diperbolehkan</strong> digunakan untuk
      kebutuhan <strong>internal organisasi</strong>,
      selama disepakati oleh para pihak dan didukung oleh sistem yang dapat
      menjamin identitas penanda tangan serta keutuhan dokumen.
    </li>
  </ul>

  <p class="text-justify">
  Berdasarkan ketentuan tersebut, penggunaan TTE Non-Sertifikasi pada aplikasi FixPoint
  <strong>dibolehkan secara hukum</strong> untuk kebutuhan internal rumah sakit,
  seperti administrasi, disposisi, persetujuan internal,
  dan dokumentasi operasional.
  Pimpinan/Direktur/Kepala Perusahaan (Rumah Sakit)
  menetapkan kebijakan penggunaan TTE Non-Sertifikasi melalui
  <strong>Surat Keputusan (SK) Penetapan Tanda Tangan Elektronik Internal</strong>
  sebagai bentuk legitimasi dan pengesahan penggunaan TTE di lingkungan rumah sakit.
</p>

<p class="text-justify">
  Selain itu, rumah sakit menyusun dan menetapkan
  <strong>Standar Prosedur Operasional (SPO/SOP)</strong>
  mengenai penggunaan TTE Non-Sertifikasi sebagai
  <strong>pedoman, acuan, dan pegangan resmi</strong>
  bagi seluruh unit kerja dalam proses pembuatan,
  pembubuhan, verifikasi, serta pengelolaan dokumen
  yang menggunakan TTE pada aplikasi FixPoint.
</p>

<p class="text-justify">
  Dengan adanya SK Direktur dan SOP TTE Non-Sertifikasi tersebut,
  maka penggunaan TTE dalam aplikasi FixPoint memiliki
  <strong>landasan kebijakan internal</strong>,
  <strong>kejelasan alur kerja</strong>,
  serta <strong>kepastian tanggung jawab</strong>
  bagi setiap pengguna, sehingga dapat mendukung
  tata kelola administrasi rumah sakit yang
  tertib, aman, dan akuntabel.
</p>

  <hr>

  <p class="text-justify">
    <strong>Contoh Dokumen Internal yang Diperbolehkan Menggunakan TTE Non-Sertifikasi:</strong>
  </p>

  <ul class="text-justify">
    <li>Surat Izin Keluar / Izin Dinas Internal</li>
    <li>Form Persetujuan Internal Unit / Instalasi</li>
    <li>Disposisi Pimpinan</li>
    <li>Berita Acara Internal</li>
    <li>Form Permintaan IT / Sarpras</li>
    <li>Dokumen Administrasi Non-Eksternal</li>
  </ul>

  <p class="text-justify">
    TTE Non-Sertifikasi <strong>tidak digunakan</strong> untuk dokumen eksternal
    yang memerlukan legalitas publik, perjanjian hukum dengan pihak ketiga,
    atau dokumen yang dipersyaratkan menggunakan TTE Tersertifikasi oleh PSrE.
  </p>

  <hr>

  <ul class="text-justify">
    <li><strong>Rekening :</strong> BSI – <code>7134197557</code></li>
    <li><strong>Atas Nama :</strong> M. Wira Satria Buana</li>
    <li><strong>Instansi :</strong> RS. Permata Hati Muara Bungo – Jambi</li>
    <li><strong>No. Tlp :</strong> 0821 7784 6209</li>
  </ul>

  <p class="text-justify">
    Setiap bentuk dukungan akan sangat berarti untuk pengembangan fitur,
    peningkatan keamanan, dan pemeliharaan sistem FixPoint ke depannya.
  </p>

</div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL AKTIVASI TTE -->
<?php
// Load license validator functions
if (file_exists('license_validator.php')) {
    require_once 'license_validator.php';
}

// Ambil data perusahaan
$perusahaan_data = null;
try {
    $perusahaan_result = $conn->query("SELECT * FROM perusahaan LIMIT 1");
    if ($perusahaan_result) {
        $perusahaan_data = $perusahaan_result->fetch_assoc();
    }
} catch (Exception $e) {
    $perusahaan_data = null;
}
?>

<div class="modal fade" id="modalAktivasiTTE" tabindex="-1" role="dialog" aria-labelledby="modalAktivasiTTELabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="modalAktivasiTTELabel">
          <i class="fas fa-key mr-2"></i> Aktivasi Fitur TTE
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      
      <form method="POST" action="activate_tte.php" id="formAktivasiTTE">
        <div class="modal-body">
          
          <!-- Info Perusahaan -->
          <?php if ($perusahaan_data): ?>
          <div class="alert alert-info">
            <strong>🏢 Perusahaan:</strong> <?php echo htmlspecialchars($perusahaan_data['nama_perusahaan']); ?><br>
            <strong>📧 Email:</strong> <?php echo htmlspecialchars($perusahaan_data['email']); ?>
          </div>
          <?php endif; ?>

          <!-- CARD PERMINTAAN LISENSI - BARU -->
          <div class="card border-success mb-3">
            <div class="card-header bg-success text-white">
              <h6 class="mb-0">
                <i class="fas fa-file-alt"></i> Belum Punya Token Lisensi?
              </h6>
            </div>
            <div class="card-body">
              <p class="mb-2" style="font-size: 13px;">
                Untuk mendapatkan <strong>token lisensi TTE</strong>, download surat permintaan, 
                isi lengkap, tandatangani + stempel, lalu kirim ke WhatsApp kami.
              </p>
              
  <div class="row">
  <div class="col-6 mb-2">
    <a href="https://docs.google.com/document/d/1l9-mea_Q_nMgMyRXv0EZIpuvylvEbFty/edit?usp=sharing&ouid=106259867665472156811&rtpof=true&sd=true" 
       class="btn btn-primary btn-sm btn-block"
       target="_blank">
        <i class="fas fa-file-word"></i> Download Surat
    </a>
  </div>
  <div class="col-6 mb-2">
    <a href="https://wa.me/6282177846209?text=Halo,%20saya%20ingin%20mengajukan%20permintaan%20lisensi%20TTE%20untuk%20FixPoint.%0A%0AData:%0ANama:%20<?php echo isset($perusahaan_data) ? urlencode($perusahaan_data['nama_perusahaan']) : ''; ?>%0AEmail:%20<?php echo isset($perusahaan_data) ? urlencode($perusahaan_data['email']) : ''; ?>" 
       class="btn btn-success btn-sm btn-block"
       target="_blank">
        <i class="fab fa-whatsapp"></i> Kirim WA
    </a>
  </div>
</div>
              
              <small class="text-muted">
                <i class="fas fa-phone"></i> <strong>0821-7784-6209</strong> | 
                Proses verifikasi maks. 1x24 jam
              </small>
            </div>
          </div>
          <!-- END CARD PERMINTAAN LISENSI -->
          
          <!-- Penjelasan TTE -->
          <div class="alert alert-light border">
            <h6><strong>ℹ️ Tentang Fitur TTE:</strong></h6>
            <p class="mb-2" style="text-align: justify;">
              Fitur <strong>Tanda Tangan Elektronik (TTE)</strong> memungkinkan Anda untuk 
              menandatangani dokumen secara digital dengan menggunakan QR Code unik yang 
              tersimpan di sistem FixPoint.
            </p>
            <p class="mb-2" style="text-align: justify;">
              TTE ini bersifat <strong>Non-Sertifikasi</strong> dan hanya digunakan untuk 
              kebutuhan <strong>internal rumah sakit</strong> seperti disposisi, persetujuan, 
              dan dokumentasi administrasi.
            </p>
            <p class="mb-0" style="text-align: justify;">
              Untuk mengaktifkan fitur ini, Anda memerlukan <strong>Token Lisensi</strong> 
              yang dapat diperoleh dari administrator sistem.
            </p>
          </div>
          
          <!-- Syarat & Ketentuan -->
          <div class="card mb-3">
            <div class="card-header bg-light">
              <strong>📋 Syarat & Ketentuan Penggunaan TTE:</strong>
            </div>
            <div class="card-body" style="max-height: 200px; overflow-y: auto;">
              <ol style="text-align: justify; font-size: 14px;">
                <li>Fitur TTE hanya dapat digunakan untuk dokumen internal rumah sakit.</li>
                <li>Setiap pengguna bertanggung jawab atas TTE yang dibubuhkan menggunakan akun mereka.</li>
                <li>TTE yang telah dibubuhkan tidak dapat dihapus atau diubah.</li>
                <li>Verifikasi TTE hanya dapat dilakukan melalui sistem FixPoint.</li>
                <li>Token lisensi hanya dapat digunakan untuk 1 email perusahaan.</li>
                <li>Penyalahgunaan TTE dapat dikenakan sanksi sesuai peraturan yang berlaku.</li>
                <li>Administrator berhak menonaktifkan TTE jika terjadi pelanggaran.</li>
              </ol>
            </div>
          </div>
          
          <!-- Checkbox Persetujuan -->
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="checkboxSetuju" required>
            <label class="form-check-label" for="checkboxSetuju">
              <strong>Saya telah membaca dan menyetujui</strong> syarat dan ketentuan penggunaan fitur TTE
            </label>
          </div>
          
          <!-- Form Token -->
          <div id="formTokenArea" style="display: none;">
            <hr>
            
            <div class="form-group">
              <label><strong>📧 Email Perusahaan:</strong></label>
              <input type="email" 
                     name="email" 
                     class="form-control" 
                     value="<?php echo $perusahaan_data ? htmlspecialchars($perusahaan_data['email']) : ''; ?>"
                     readonly 
                     required>
              <small class="form-text text-muted">Email ini harus sesuai dengan yang digunakan saat generate token</small>
            </div>
            
            <div class="form-group">
              <label><strong>🔑 Token Lisensi:</strong></label>
              <input type="text" 
                     name="token" 
                     class="form-control form-control-lg token-input" 
                     placeholder="FIXPOINT-XXXXX-XXXXX-XXXXX"
                     maxlength="28"
                     style="text-transform: uppercase; font-family: 'Courier New', monospace; letter-spacing: 2px;"
                     required>
              <small class="form-text text-muted">Masukkan token lisensi yang Anda terima dari Pemilik aplikasi (M. Wira Satria Buana - 082177846209)</small>
            </div>
            
            <div class="alert alert-warning">
              <strong>⚠️ Penting:</strong>
              <ul class="mb-0 pl-3" style="font-size: 13px;">
                <li>Token bersifat <strong>case-sensitive</strong> (huruf besar semua)</li>
                <li>Format: <code>FIXPOINT-XXXXX-XXXXX-XXXXX</code> (28 karakter)</li>
                <li>1 token hanya untuk 1 email perusahaan</li>
              </ul>
            </div>
          </div>
          
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success" id="btnAktivasi" disabled>
            <i class="fas fa-unlock"></i> Aktivasi TTE
          </button>
        </div>
      </form>
      
    </div>
  </div>
</div>

<script>
// Script untuk mengaktifkan form token setelah checkbox dicentang
document.getElementById('checkboxSetuju').addEventListener('change', function() {
  const formTokenArea = document.getElementById('formTokenArea');
  const btnAktivasi = document.getElementById('btnAktivasi');
  
  if (this.checked) {
    formTokenArea.style.display = 'block';
    btnAktivasi.disabled = false;
  } else {
    formTokenArea.style.display = 'none';
    btnAktivasi.disabled = true;
  }
});
</script>

<!-- SCRIPT -->
<script src="assets/modules/jquery.min.js"></script>
<script src="assets/modules/bootstrap/js/bootstrap.min.js"></script>

<script>
$(document).ready(function () {
  // Search menu
  $('#searchMenu').on('keyup', function () {
    var keyword = $(this).val().toLowerCase();
    $('#menuList li.dropdown').each(function () {
      var found = false;
      $(this).find('span').each(function () {
        if ($(this).text().toLowerCase().indexOf(keyword) > -1) {
          found = true;
        }
      });
      $(this).toggle(found);
    });

    $('#menuList .menu-header').each(function () {
      var nextDropdown = $(this).nextUntil('.menu-header');
      var anyVisible = nextDropdown.filter(':visible').length > 0;
      $(this).toggle(anyVisible);
    });

    if (keyword !== '') {
      $('#menuList .dropdown-menu').show();
    } else {
      $('#menuList .dropdown-menu').hide();
    }
  });

  // Fix modal backdrop
  $('#tentangModal, #tentangModalTTE, #modalAktivasiTTE').on('hidden.bs.modal', function () {
    $('body').removeClass('modal-open');
    $('.modal-backdrop').remove();
  });

  // Checkbox aktivasi TTE
  $('#checkboxSetuju').on('change', function() {
    if ($(this).is(':checked')) {
      $('#formTokenArea').slideDown();
      $('#btnAktivasi').prop('disabled', false);
    } else {
      $('#formTokenArea').slideUp();
      $('#btnAktivasi').prop('disabled', true);
    }
  });

  // Auto-format token input
  $('input[name="token"]').on('input', function(e) {
    let value = e.target.value.replace(/[^A-Z0-9-]/gi, '').toUpperCase();
    value = value.replace(/-/g, '');
    
    if (value.startsWith('FIXPOINT')) {
      value = value.substring(8);
      let formatted = 'FIXPOINT';
      if (value.length > 0) formatted += '-' + value.substring(0, 5);
      if (value.length > 5) formatted += '-' + value.substring(5, 10);
      if (value.length > 10) formatted += '-' + value.substring(10, 15);
      e.target.value = formatted;
    } else {
      e.target.value = value;
    }
  });
});
</script>
