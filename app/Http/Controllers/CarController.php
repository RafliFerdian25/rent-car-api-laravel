<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseFormatter;
use App\Models\Car;
use App\Models\Brand;
use App\Models\CarType;
use Illuminate\Http\Request;
use App\Http\Requests\StoreCarRequest;
use App\Http\Requests\UpdateCarRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CarController extends Controller
{
    // Get data car
    public function index(Request $request)
    {
        // mengecek apakah terdapat filter tanggal mobil tersedia
        if ($request->filterStartDate xor $request->filterEndDate) {
            return ResponseFormatter::error([
                'error' => 'Tanggal mulai dan tanggal selesai harus diisi'
            ], 'Validasi gagal', 422);
        }

        // mengambil data mobil dengan relasi brand, carType, dan rents
        $cars = Car::with('brand:id,name', 'carType:id,name')
            ->select('id', 'name', 'brand_id', 'car_type_id', 'rental_rate', 'license_plate')
            ->when($request->filterBrand, function ($query) use ($request) {
                $query->where('brand_id', $request->filterBrand);
            })
            ->when($request->filterCarType, function ($query) use ($request) {
                $query->where('car_type_id', $request->filterCarType);
            })
            ->when($request->filterStartDate && $request->filterEndDate, function ($query) use ($request) {
                $startDate = Carbon::parse($request->filterStartDate);
                $endDate = Carbon::parse($request->filterEndDate);
                $query->whereDoesntHave('rents', function ($query) use ($startDate, $endDate) {
                    $query->where(function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('start_date', [$startDate, $endDate])
                            ->orWhereBetween('end_date', [$startDate, $endDate]);
                    });
                });
            })
            ->get();

        return ResponseFormatter::success([
            'cars' => $cars
        ], 'Data mobil berhasil diambil');
    }

    public function show($id)
    {
        // ambil data mobil
        $car = Car::find($id);
        if (!$car) {
            return ResponseFormatter::error([
                'error' => 'Data mobil tidak ditemukan'
            ], 'Data mobil gagal diambil', 404);
        }

        return ResponseFormatter::success([
            'car' => $car
        ], 'Data mobil berhasil diambil');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // validasi data yang diterima
        $rules = [
            'name' => 'required|string',
            'brand_id' => 'required|exists:brands,id',
            'car_type_id' => 'required|exists:car_types,id',
            'rental_rate' => 'required|numeric',
            'license_plate' => 'required|string|unique:cars,license_plate',
        ];

        $validated = Validator::make($request->all(), $rules);

        if ($validated->fails()) {
            return ResponseFormatter::error([
                'message' => $validated->errors()
            ], 'Validasi gagal', 422);
        }

        try {
            // simpan data mobil
            DB::beginTransaction();
            Car::create($request->all());
            DB::commit();

            return ResponseFormatter::success(null, 'Data mobil berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            DB::rollback();
            return ResponseFormatter::error([
                'error' => $e->getMessage()
            ], 'Data mobil gagal ditambahkan', 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // ambil data mobil
        $car = Car::find($id);
        if (!$car) {
            return ResponseFormatter::error([
                'error' => 'Data mobil tidak ditemukan'
            ], 'Data mobil gagal diubah', 404);
        }

        $rules = [
            'name' => 'required|string',
            'brand_id' => 'required|exists:brands,id',
            'car_type_id' => 'required|exists:car_types,id',
            'rental_rate' => 'required|numeric',
            'license_plate' => 'required|string|unique:cars,license_plate,' . $car->id . ',id',
        ];

        $validated = Validator::make($request->all(), $rules);

        if ($validated->fails()) {
            return ResponseFormatter::error([
                'message' => $validated->errors()
            ], 'Validasi gagal', 422);
        }

        try {
            DB::beginTransaction();
            // ubah data mobil
            $car->update($request->all());

            DB::commit();
            return ResponseFormatter::success([
                'redirect' => route('car.index'),
            ], 'Data mobil berhasil diubah');
        } catch (\Exception $e) {
            DB::rollback();
            return ResponseFormatter::error([
                'message' => $e->getMessage()
            ], 'Data mobil gagal diubah', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // ambil data mobil
        $car = Car::find($id);
        if (!$car) {
            return ResponseFormatter::error([
                'error' => 'Data mobil tidak ditemukan'
            ], 'Data mobil gagal dihapus', 404);
        }

        try {
            DB::beginTransaction();
            $car->delete();
            DB::commit();

            return ResponseFormatter::success(null, 'Data mobil berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollback();
            return ResponseFormatter::error([
                'message' => $e->getMessage()
            ], 'Data mobil gagal dihapus', 500);
        }
    }
}
