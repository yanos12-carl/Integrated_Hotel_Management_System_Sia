<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Paginator, Request, Response};
use App\Services\SecurityService;

final class SecurityController extends BaseController
{
    public function summary(Request $r, array $p = []): Response { return Response::success(SecurityService::summary()); }
    public function users(Request $r, array $p = []): Response { return Response::success(SecurityService::securityUsers()); }

    public function incidents(Request $r, array $p = []): Response
    {
        [$page, $per, $off] = Paginator::params($r);
        [$rows, $total] = SecurityService::incidents($r->query, $per, $off);
        return $this->paged($rows, $total, $page, $per);
    }
    public function incidentShow(Request $r, array $p = []): Response { return Response::success(SecurityService::incident((int) $p['id'])); }
    public function incidentCreate(Request $r, array $p = []): Response { return Response::created(SecurityService::createIncident($r->body, $this->uid($r)), 'Incident reported.'); }
    public function incidentUpdate(Request $r, array $p = []): Response { return Response::created(SecurityService::addIncidentUpdate((int) $p['id'], $r->body, $this->uid($r)), 'Incident update recorded.'); }
    public function incidentStatus(Request $r, array $p = []): Response { return Response::success(SecurityService::updateIncidentStatus((int) $p['id'], $r->body, $this->uid($r)), 'Incident status updated.'); }
    public function incidentAssign(Request $r, array $p = []): Response { return Response::success(SecurityService::assignIncident((int) $p['id'], $r->body, $this->uid($r)), 'Incident assigned.'); }

    public function cctvReferences(Request $r, array $p = []): Response
    {
        [$page, $per, $off] = Paginator::params($r);
        [$rows, $total] = SecurityService::cctvReferences($per, $off);
        return $this->paged($rows, $total, $page, $per);
    }
    public function cctvCreate(Request $r, array $p = []): Response { return Response::created(SecurityService::addCctvReference($r->body, $this->uid($r)), 'CCTV reference recorded.'); }
    public function cameras(Request $r, array $p = []): Response { return Response::success(SecurityService::cameras()); }
    public function cameraSave(Request $r, array $p = []): Response { return Response::success(SecurityService::saveCamera($r->body, $this->uid($r)), 'Camera setup saved.'); }
    public function cameraRemove(Request $r, array $p = []): Response { SecurityService::removeCamera((int) $p['id'], $this->uid($r)); return Response::success(null, 'Camera removed.'); }

    public function visitors(Request $r, array $p = []): Response
    {
        [$page, $per, $off] = Paginator::params($r);
        [$rows, $total] = SecurityService::visitors($r->query, $per, $off);
        return $this->paged($rows, $total, $page, $per);
    }
    public function visitorCreate(Request $r, array $p = []): Response { return Response::created(SecurityService::checkInVisitor($r->body, $this->uid($r)), 'Visitor checked in.'); }
    public function visitorCheckout(Request $r, array $p = []): Response { return Response::success(SecurityService::checkOutVisitor((int) $p['id'], $this->uid($r)), 'Visitor checked out.'); }

    public function shifts(Request $r, array $p = []): Response
    {
        [$page, $per, $off] = Paginator::params($r);
        [$rows, $total] = SecurityService::shifts($r->query, $per, $off);
        return $this->paged($rows, $total, $page, $per);
    }
    public function shiftCreate(Request $r, array $p = []): Response { return Response::created(SecurityService::createShift($r->body, $this->uid($r)), 'Guard shift scheduled.'); }
    public function shiftStatus(Request $r, array $p = []): Response { return Response::success(SecurityService::setShiftStatus((int) $p['id'], $r->body, $this->uid($r)), 'Shift status updated.'); }

    public function handovers(Request $r, array $p = []): Response
    {
        [$page, $per, $off] = Paginator::params($r);
        [$rows, $total] = SecurityService::handovers($per, $off);
        return $this->paged($rows, $total, $page, $per);
    }
    public function handoverCreate(Request $r, array $p = []): Response { return Response::created(SecurityService::createHandover($r->body, $this->uid($r)), 'Shift handover recorded.'); }
    public function handoverAcknowledge(Request $r, array $p = []): Response { return Response::success(SecurityService::acknowledgeHandover((int) $p['id'], $this->uid($r)), 'Handover acknowledged.'); }

    public function patrolSchedules(Request $r, array $p = []): Response { return Response::success(SecurityService::patrolSchedules()); }
    public function patrolScheduleCreate(Request $r, array $p = []): Response { return Response::created(SecurityService::createPatrolSchedule($r->body, $this->uid($r)), 'Patrol schedule created.'); }
    public function checkpoints(Request $r, array $p = []): Response { return Response::success(SecurityService::checkpoints()); }
    public function checkpointCreate(Request $r, array $p = []): Response { return Response::created(SecurityService::createCheckpoint($r->body, $this->uid($r)), 'Patrol checkpoint created.'); }
    public function patrolRecords(Request $r, array $p = []): Response
    {
        [$page, $per, $off] = Paginator::params($r);
        [$rows, $total] = SecurityService::patrolRecords($per, $off);
        return $this->paged($rows, $total, $page, $per);
    }
    public function patrolRecordCreate(Request $r, array $p = []): Response { return Response::created(SecurityService::recordPatrol($r->body, $this->uid($r)), 'Patrol checkpoint recorded.'); }

    public function inspections(Request $r, array $p = []): Response
    {
        [$page, $per, $off] = Paginator::params($r);
        [$rows, $total] = SecurityService::inspections($per, $off);
        return $this->paged($rows, $total, $page, $per);
    }
    public function inspectionCreate(Request $r, array $p = []): Response { return Response::created(SecurityService::createInspection($r->body, $this->uid($r)), 'Safety inspection recorded.'); }
}
