<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\Quotation;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lightweight CRM overview — counts and queues, not a second dashboard.
 * Full analytics stays in a later phase.
 */
class CrmDashboardController extends Controller
{
    public function index(): Response
    {
        $openLeads = Lead::whereNotIn('status', ['won', 'lost']);

        return Inertia::render('Admin/CRM/Dashboard', [
            'stats' => [
                'new_leads' => (clone $openLeads)->where('status', 'new')->count(),
                'hot_leads' => (clone $openLeads)->where('priority', 'hot')->count(),
                'unassigned' => (clone $openLeads)->whereNull('assigned_to')->count(),
                'due_today' => LeadFollowUp::pending()->whereDate('due_at', today())->count(),
                'overdue' => LeadFollowUp::overdue()->count(),
                'quotations_sent' => Quotation::whereIn('status', ['sent', 'viewed'])->count(),
                'quotations_accepted' => Quotation::where('status', 'accepted')->count(),
                'won' => Lead::where('status', 'won')->count(),
                'lost' => Lead::where('status', 'lost')->count(),
            ],
            'hotLeads' => (clone $openLeads)->where('priority', 'hot')
                ->with(['assignee:id,name', 'source:id,name'])
                ->latest()->limit(8)->get(),
            'overdueFollowUps' => LeadFollowUp::overdue()
                ->with(['lead:id,reference,name', 'assignee:id,name'])
                ->orderBy('due_at')->limit(8)->get(),
            'recentQuotations' => Quotation::with(['lead:id,reference', 'customer:id,name'])
                ->latest()->limit(8)->get(),
        ]);
    }
}
