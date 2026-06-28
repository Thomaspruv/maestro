<?php

namespace App\Http\Controllers\PipelineSteps;

use App\Http\Controllers\Controller;
use App\Http\Requests\PipelineSteps\UpdateOutputRequest;
use App\Models\PipelineStep;
use Illuminate\Http\RedirectResponse;

class PipelineStepController extends Controller
{
    public function updateOutput(UpdateOutputRequest $request, PipelineStep $step): RedirectResponse
    {
        $step->update(['edited_output' => $request->validated('edited_output')]);

        return back()->with('success', 'Output enregistré.');
    }
}
