<?php

namespace App\Http\Controllers\Gates;

use App\Enums\GateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gates\RejectGateRequest;
use App\Models\Gate;
use App\Services\GateReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GateController extends Controller
{
    public function approve(Request $request, Gate $gate, GateReviewService $gateReview): RedirectResponse
    {
        $this->authorize('update', $gate);

        $gateReview->approve(
            $gate,
            $request->filled('edited_output') ? $request->input('edited_output') : null,
        );

        return back()->with('success', 'Gate validée — pipeline relancé.');
    }

    public function reject(RejectGateRequest $request, Gate $gate, GateReviewService $gateReview): RedirectResponse
    {
        $gateReview->reject($gate, $request->validated('feedback'));

        if ($gate->fresh()->status === GateStatus::Rejected) {
            return back()->with('error', 'Maximum de régénérations atteint. Intervention manuelle requise.');
        }

        return back()->with('success', 'Feedback envoyé — l\'agent régénère.');
    }
}
