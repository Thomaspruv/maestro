<?php

namespace App\Http\Controllers\AgentRuns;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentRuns\UpdateOutputRequest;
use App\Models\AgentRun;
use Illuminate\Http\RedirectResponse;

class AgentRunController extends Controller
{
    public function updateOutput(UpdateOutputRequest $request, AgentRun $run): RedirectResponse
    {
        $run->update(['edited_output' => $request->validated('edited_output')]);

        return back()->with('success', 'Output enregistré.');
    }
}
