<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseFormatter;
use App\Models\Rent;
use App\Http\Requests\StoreRentRequest;
use App\Http\Requests\UpdateRentRequest;
use App\Models\Car;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RentController extends Controller
{
    public function index(Request $request)
    {
        if (auth()->user()->role == 'admin') {
            $rents = Rent::with('car:id,name,license_plate,rental_rate', 'user:id,name,address,phone,driving_license')
                ->when(
                    $request->status,
                    function ($query) use ($request) {
                        $query->where('status', $request->status);
                    },
                    function ($query) {
                        $query->where('status', '!=', 'kembali');
                    }
                )
                ->get();
        } else {
            $rents = Rent::with('car:id,name,license_plate,rental_rate')->where('user_id', auth()->user()->id)
                ->when(
                    $request->status,
                    function ($query) use ($request) {
                        $query->where('status', $request->status);
                    },
                    function ($query) {
                        $query->where('status', '!=', 'kembali');
                    }
                )
                ->get();
        }

        return ResponseFormatter::success([
            'rents' => $rents,
        ], 'Data peminjaman berhasil diambil');
    }

    public function show($id)
    {
        $rent = Rent::with('car:id,name,license_plate,rental_rate', 'user:id,name,address,phone,driving_license')->find($id);

        // mengecek apakah data peminjaman ditemukan
        if (!$rent) {
            return ResponseFormatter::error([
                'error' => 'Data peminjaman tidak ditemukan',
            ], 'Data peminjaman gagal diambil', 404);
        }

        // mengecek apakah data peminjaman milik user yang login
        if (auth()->user()->id != $rent->user_id && auth()->user()->role != 'admin') {
            return ResponseFormatter::error([
                'error' => 'Data peminjaman tidak ditemukan',
            ], 'Data peminjaman gagal diambil', 404);
        }

        return ResponseFormatter::success([
            'rent' => $rent,
        ], 'Data peminjaman berhasil diambil');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $rules = [
            'car_id' => 'required|exists:cars,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ];

        $validated = Validator::make($request->all(), $rules);

        if ($validated->fails()) {
            return ResponseFormatter::error(
                $validated->errors(),
                'Validation Error',
                422
            );
        }

        // mengecek jika mobil tersedia
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        if ($startDate->isBefore(Carbon::now()->subDay())) {
            return ResponseFormatter::error([
                'error' => 'Tanggal mulai harus lebih dari hari ini',
            ], 'Peminjaman Gagal', 500);
        }

        if ($endDate->isBefore($startDate)) {
            return ResponseFormatter::error([
                'error' => 'Tanggal selesai harus lebih dari tanggal mulai',
            ], 'Peminjaman Gagal', 500);
        }

        $checkRent = Rent::where('car_id', $request->car_id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })
            ->where('status', 'pinjam')
            ->exists();

        try {
            DB::beginTransaction();
            if ($checkRent) {
                throw new \Exception('Mobil Tidak Tersedia');
            } else {
                // simpan data peminjaman
                $rent = Rent::create([
                    'car_id' => $request->car_id,
                    'user_id' => auth()->user()->id,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                ]);

                if (!$rent) {
                    throw new \Exception('Peminjaman Gagal');
                }

                DB::commit();
                return ResponseFormatter::success([
                    'rent' => $rent,
                ], 'Peminjaman Berhasil');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseFormatter::error([
                'error' => $e->getMessage()
            ], 'Peminjaman Gagal', 500);
        }
    }

    public function return(Rent $rent, Request $request)
    {
        // mengecek apakah mobil sudah dikembalikan
        if ($rent->status == 'kembali') {
            return ResponseFormatter::error([
                'error' => 'Mobil sudah dikembalikan',
            ], 'Pengembalian Gagal', 500);
        }

        // mengecek jika mobil belum pada waktu pinjam
        $startDate = Carbon::parse($rent->start_date);
        $returnDate = $request->return_date ?? Carbon::now();

        if (Carbon::now()->isBefore($startDate)) {
            return ResponseFormatter::error([
                'error' => 'Belum tanggal mulai pinjam',
            ], 'Pengembalian Gagal', 500);
        }

        // mengecek apakah mobil dikembalikan yang meminjam
        if ($rent->user_id != auth()->user()->id) {
            return ResponseFormatter::error([
                'error' => 'Anda tidak bisa mengembalikan mobil yang dipinjam oleh orang lain',
            ], 'Pengembalian Gagal', 500);
        }

        // menghitung jumlah yang harus dibayar
        $totalDays = round($startDate->diffInDays($returnDate));
        $totalDays = $totalDays > 0 ? $totalDays : 1;
        $amount = $totalDays * $rent->car->rental_rate;

        $rent->update([
            'status' => 'kembali',
            'return_date' => $returnDate,
            'amount' => $amount,
        ]);

        return ResponseFormatter::success([
            'rent' => $rent,
        ], 'Pengembalian Berhasil');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $rent = Rent::find($id);
        // authorize user
        if (auth()->user()->id != $rent->user_id && auth()->user()->role != 'admin') {
            return ResponseFormatter::error([
                'error' => 'Data peminjaman tidak ditemukan',
            ], 'Data peminjaman gagal dihapus', 404);
        }

        // mengecek jika mobil pada waktu pinjam
        $startDate = Carbon::parse($rent->start_date);
        $endDate = Carbon::parse($rent->end_date);

        if (Carbon::now()->between($startDate, $endDate)) {
            return ResponseFormatter::error([
                'error' => 'Tidak bisa menghapus peminjaman saat ini',
            ], 'Peminjaman Gagal', 500);
        } else if (Carbon::now()->isAfter($endDate)) {
            return ResponseFormatter::error([
                'error' => 'Peminjaman sudah berakhir',
            ], 'Peminjaman Gagal', 500);
        }

        $rent->delete();

        return ResponseFormatter::success(null, 'Peminjaman Berhasil Dihapus');
    }
}
