<?php

namespace App\Http\Controllers\TOS\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TOS\Admin\StoreDeploymentRequest;
use App\Http\Requests\TOS\Admin\UpdateDeploymentRequest;
use App\Models\TOS\Deployment;
use App\Traits\TOS\HandlesTosImageUpload;
use Illuminate\Http\Request;

class DeploymentAdminController extends Controller
{
    use HandlesTosImageUpload;

    public function index(Request $request)
    {
        return inertia('Admin/TOS/Deployments/Index', [
            'deployments' => Deployment::orderBy('sort_order')->orderBy('name')->get(['id', 'slug', 'name', 'suit', 'sort_order']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/TOS/Deployments/DeploymentForm');
    }

    public function edit(Request $request, Deployment $deployment)
    {
        return inertia('Admin/TOS/Deployments/DeploymentForm', [
            'deployment' => $deployment,
        ]);
    }

    public function store(StoreDeploymentRequest $request)
    {
        $data = $request->validated();
        $data['image_path'] = $this->storeTosImage($request->file('image_path'), 'tos/deployments');

        $deployment = Deployment::create($data);

        return redirect()->route('admin.tos.deployments.index')->withMessage("{$deployment->name} created.");
    }

    public function update(UpdateDeploymentRequest $request, Deployment $deployment)
    {
        $data = $request->validated();

        if ($request->hasFile('image_path')) {
            $this->deleteTosImage($deployment->image_path);
            $data['image_path'] = $this->storeTosImage($request->file('image_path'), 'tos/deployments');
        } else {
            unset($data['image_path']);
        }

        $deployment->update($data);

        return redirect()->route('admin.tos.deployments.index')->withMessage("{$deployment->name} updated.");
    }

    public function delete(Request $request, Deployment $deployment)
    {
        $name = $deployment->name;
        $deployment->delete();

        return redirect()->route('admin.tos.deployments.index')->withMessage("{$name} deleted.");
    }
}
