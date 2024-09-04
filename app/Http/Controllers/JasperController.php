<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PHPJasper\PHPJasper;

class JasperController extends Controller
{
    // generate jasper report (nama_report, format_report, []parameters) untuk parameters opsional
    public function generateReport(Request $request)
    {
        $namaReport = $request->input('nama_report');
        $formatReport = $request->input('format_report');

        if (!$namaReport) {
            return redirect()->back()->withErrors(['nama_report' => 'Nama laporan tidak diberikan']);
        }

        if (!$formatReport) {
            return redirect()->back()->withErrors(['format_report' => 'Format laporan tidak diberikan']);
        }

        $uniqueId = uniqid();
        $outputDir = storage_path('app/report/output/' . $namaReport);
        // Pastikan direktori output ada
        $this->dir_check($outputDir);

        $input = storage_path('app/report/compiled/' . $namaReport . '.jasper');
        // Cek jika file .jasper ada
        if (!file_exists($input)) {
            return redirect()->back()->withErrors(['report' => 'File laporan tidak ditemukan']);
        }

        $outputFile = $outputDir . '/' . $namaReport . '_' . $uniqueId;

        $parameters = $request->except('nama_report', '_token', 'format_report');
        $options = [
            'format' => [$formatReport],
            'locale' => 'en',
            'db_connection' => [
                'driver' => env('JASPER_DRIVER', 'mysql'),
                'host' => env('DB_HOST'),
                'port' => env('DB_PORT'),
                'database' => env('DB_DATABASE'),
                'username' => env('DB_USERNAME'),
                // kalau password = '' comment saja
                // 'password' => env('DB_PASSWORD', ''),
            ]
        ];

        // Tambahkan params hanya jika tidak kosong
        if (!empty($parameters)) {
            $options['params'] = $parameters;
        }

        try {
            $jasper = new PHPJasper;
            $jasper->process(
                $input,
                $outputFile,
                $options
            )->execute();

            return response()->file($outputFile . '.'.$formatReport);

        } catch (\Exception $e) {
            dd($e->getMessage());
            return redirect()->back()->withErrors(['report' => 'Terjadi kesalahan saat memproses laporan: ' . $e->getMessage()]);
        }
    }

    // cek directory jika belum ada, maka akan dibuat folder
    private function dir_check($path)
    {
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
    }

    // convert jrxml dari /src_jrxml menjadi jasper ke folder compiled. jika sudah ada maka dihapus dan convert ulang file jasper nya
    public static function UpdateReports($file = "")
    {
        $sourcePath = storage_path('app/report/source/src_jrxml'); // Path ke folder source
        $compiledPath = storage_path('app/report/compiled'); // Path ke folder compiled

        if ($file != "") {
            $file .= ".jrxml";
            $filePath = $sourcePath . "/" . $file;
            if (file_exists($filePath)) {
                $compiledFilePath = $compiledPath . "/" . pathinfo($file, PATHINFO_FILENAME) . '.jasper';
                if (file_exists($compiledFilePath)) {
                    // Hapus file .jasper jika ada
                    unlink($compiledFilePath);
                }
                self::compile($filePath);
                return "Build Successfully!";
            } else {
                return "Sorry! No Report file found in " . $sourcePath;
            }
        } else {
            $reports = self::read_files($sourcePath);
            foreach ($reports as $filePath) {
                $compiledFilePath = $compiledPath . "/" . pathinfo($filePath, PATHINFO_FILENAME) . '.jasper';
                if (file_exists($compiledFilePath)) {
                    // Hapus file .jasper jika ada
                    unlink($compiledFilePath);
                }
                self::compile($filePath);
            }
            return "All Reports Build Successfully!";
        }
    }

    public static function compile($input)
    {
        $output = storage_path('app/report/compiled');
        $jasper = new PHPJasper;
        $jasper->compile($input, $output)->execute();
    }

    // cek file nya ada atau tidak dan  menghasilkan list path + nama file
    public static function read_files($path)
    {
        $files = [];
        foreach (new \DirectoryIterator($path) as $file) {
            if ($file->isFile() && $file->getExtension() === 'jrxml') {
                $files[$file->getFilename()] = $path . "/" . $file->getFilename();
            }
        }
        return $files;
    }
}
