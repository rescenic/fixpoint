<?php
/**
 * ============================================================
 * CLASS NomorDokumenGenerator
 * Sistem Penomoran Dokumen Otomatis
 * ============================================================
 */

class NomorDokumenGenerator
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * ============================================================
     * PREVIEW NOMOR (TIDAK MENYIMPAN KE DB)
     * ============================================================
     */
    public function previewNomorBerikutnya(string $jenis_dokumen, int $unit_kerja_id): string
    {
        $data = $this->getMasterData($jenis_dokumen, $unit_kerja_id, false);
        $nomor_urut = $data['nomor_terakhir'] + 1;

        return $this->formatNomor(
            $nomor_urut,
            $data['kode_dokumen'],
            $data['kode_unit'],
            $data['bulan'],
            $data['tahun']
        );
    }

    /**
     * ============================================================
     * GENERATE NOMOR REAL (MENYIMPAN KE DB)
     * ============================================================
     */
    public function generateNomor(
        string $jenis_dokumen,
        int $unit_kerja_id,
        string $tabel_referensi,
        int $referensi_id,
        int $user_id
    ): array {

        $this->conn->begin_transaction();

        try {
            // Ambil master + LOCK
            $data = $this->getMasterData($jenis_dokumen, $unit_kerja_id, true);

            $nomor_urut_baru = $data['nomor_terakhir'] + 1;

            $nomor_lengkap = $this->formatNomor(
                $nomor_urut_baru,
                $data['kode_dokumen'],
                $data['kode_unit'],
                $data['bulan'],
                $data['tahun']
            );

            // Update nomor terakhir
            $stmt = $this->conn->prepare("
                UPDATE master_nomor_dokumen
                SET nomor_terakhir = ?, updated_at = NOW()
                WHERE id = ?
            ");
            if (!$stmt) {
                throw new Exception("SQL ERROR update master: " . $this->conn->error);
            }

            $stmt->bind_param("ii", $nomor_urut_baru, $data['master_id']);
            $stmt->execute();

            // Insert log
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $stmtLog = $this->conn->prepare("
                INSERT INTO log_penomoran
                (
                    master_nomor_id,
                    nomor_lengkap,
                    tabel_referensi,
                    referensi_id,
                    digunakan_oleh,
                    ip_address,
                    user_agent
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmtLog) {
                throw new Exception("SQL ERROR log_penomoran: " . $this->conn->error);
            }

            $stmtLog->bind_param(
                "ississs",
                $data['master_id'],
                $nomor_lengkap,
                $tabel_referensi,
                $referensi_id,
                $user_id,
                $ip_address,
                $user_agent
            );
            $stmtLog->execute();

            $log_id = $stmtLog->insert_id;

            $this->conn->commit();

            return [
                'nomor_lengkap' => $nomor_lengkap,
                'master_id'     => $data['master_id'],
                'log_id'        => $log_id
            ];

        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * ============================================================
     * AMBIL MASTER DATA (PAKAI TAHUN & BULAN)
     * ============================================================
     */
    private function getMasterData(string $jenis_dokumen, int $unit_kerja_id, bool $lock): array
    {
        $tahun = (int)date('Y');
        $bulan = (int)date('n');

        // Ambil jenis dokumen
        $stmtJenis = $this->conn->prepare("
            SELECT id, kode_dokumen
            FROM jenis_dokumen
            WHERE kode_dokumen = ? AND aktif = 1
            LIMIT 1
        ");
        if (!$stmtJenis) {
            throw new Exception("SQL ERROR jenis_dokumen: " . $this->conn->error);
        }

        $stmtJenis->bind_param("s", $jenis_dokumen);
        $stmtJenis->execute();
        $jenis = $stmtJenis->get_result()->fetch_assoc();

        if (!$jenis) {
            throw new Exception("Jenis dokumen '$jenis_dokumen' tidak ditemukan");
        }

        // Ambil unit kerja
        $stmtUnit = $this->conn->prepare("
            SELECT id, kode_unit
            FROM unit_kerja
            WHERE id = ? AND kode_unit IS NOT NULL
            LIMIT 1
        ");
        if (!$stmtUnit) {
            throw new Exception("SQL ERROR unit_kerja: " . $this->conn->error);
        }

        $stmtUnit->bind_param("i", $unit_kerja_id);
        $stmtUnit->execute();
        $unit = $stmtUnit->get_result()->fetch_assoc();

        if (!$unit) {
            throw new Exception("Unit kerja belum memiliki kode");
        }

        // Ambil master sesuai tahun & bulan
        $sqlMaster = "
            SELECT *
            FROM master_nomor_dokumen
            WHERE jenis_dokumen_id = ?
              AND unit_kerja_id = ?
              AND tahun = ?
              AND bulan = ?
            LIMIT 1
        ";

        if ($lock) {
            $sqlMaster .= " FOR UPDATE";
        }

        $stmtMaster = $this->conn->prepare($sqlMaster);
        if (!$stmtMaster) {
            throw new Exception("SQL ERROR master_nomor_dokumen: " . $this->conn->error);
        }

        $stmtMaster->bind_param(
            "iiii",
            $jenis['id'],
            $unit_kerja_id,
            $tahun,
            $bulan
        );
        $stmtMaster->execute();
        $master = $stmtMaster->get_result()->fetch_assoc();

        // Jika belum ada → buat baru (reset otomatis)
        if (!$master) {
            $stmtInsert = $this->conn->prepare("
                INSERT INTO master_nomor_dokumen
                (jenis_dokumen_id, unit_kerja_id, tahun, bulan, nomor_terakhir)
                VALUES (?, ?, ?, ?, 0)
            ");
            if (!$stmtInsert) {
                throw new Exception("SQL ERROR insert master: " . $this->conn->error);
            }

            $stmtInsert->bind_param(
                "iiii",
                $jenis['id'],
                $unit_kerja_id,
                $tahun,
                $bulan
            );
            $stmtInsert->execute();

            $master = [
                'id'             => $stmtInsert->insert_id,
                'nomor_terakhir' => 0
            ];
        }

        return [
            'master_id'      => $master['id'],
            'nomor_terakhir' => (int)$master['nomor_terakhir'],
            'kode_dokumen'   => $jenis['kode_dokumen'],
            'kode_unit'      => $unit['kode_unit'],
            'tahun'          => $tahun,
            'bulan'          => $bulan
        ];
    }

    /**
     * ============================================================
     * FORMAT NOMOR DOKUMEN
     * ============================================================
     */
    private function formatNomor(
        int $urut,
        string $kode_dokumen,
        string $kode_unit,
        int $bulan,
        int $tahun
    ): string {
        $bulan_romawi = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];

        $urut_format = str_pad($urut, 3, '0', STR_PAD_LEFT);
        $bulan_rom = $bulan_romawi[$bulan];

        return "{$urut_format}/{$kode_dokumen}/{$kode_unit}/RSPH/{$bulan_rom}/{$tahun}";
    }
}
