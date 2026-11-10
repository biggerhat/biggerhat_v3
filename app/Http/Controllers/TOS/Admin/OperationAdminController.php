<?php

namespace App\Http\Controllers\TOS\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TOS\Admin\StoreOperationRequest;
use App\Http\Requests\TOS\Admin\UpdateOperationRequest;
use App\Models\TOS\Operation;
use App\Traits\TOS\HandlesTosImageUpload;
use Illuminate\Http\Request;

class OperationAdminController extends Controller
{
    use HandlesTosImageUpload;

    public function index(Request $request)
    {
        return inertia('Admin/TOS/Operations/Index', [
            'operations' => Operation::orderBy('sort_order')->orderBy('name')->get(['id', 'slug', 'name', 'source', 'sort_order']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/TOS/Operations/OperationForm');
    }

    public function edit(Request $request, Operation $operation)
    {
        return inertia('Admin/TOS/Operations/OperationForm', [
            'operation' => $operation,
        ]);
    }

    public function store(StoreOperationRequest $request)
    {
        $data = $request->validated();
        $data['image_path'] = $this->storeTosImage($request->file('image_path'), 'tos/operations');

        $operation = Operation::create($data);

        return redirect()->route('admin.tos.operations.index')->withMessage("{$operation->name} created.");
    }

    public function update(UpdateOperationRequest $request, Operation $operation)
    {
        $data = $request->validated();

        if ($request->hasFile('image_path')) {
            $this->deleteTosImage($operation->image_path);
            $data['image_path'] = $this->storeTosImage($request->file('image_path'), 'tos/operations');
        } else {
            unset($data['image_path']);
        }

        $operation->update($data);

        return redirect()->route('admin.tos.operations.index')->withMessage("{$operation->name} updated.");
    }

    public function delete(Request $request, Operation $operation)
    {
        $name = $operation->name;
        $operation->delete();

        return redirect()->route('admin.tos.operations.index')->withMessage("{$name} deleted.");
    }
}
