# Graph Report - qs-manager\qs-manager-v2  (2026-07-13)

## Corpus Check
- 67 files · ~22,771 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 459 nodes · 644 edges · 46 communities (19 shown, 27 thin omitted)
- Extraction: 94% EXTRACTED · 6% INFERRED · 0% AMBIGUOUS · INFERRED: 41 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 3|Community 3]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 6|Community 6]]
- [[_COMMUNITY_Community 7|Community 7]]
- [[_COMMUNITY_Community 8|Community 8]]
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 11|Community 11]]
- [[_COMMUNITY_Community 12|Community 12]]
- [[_COMMUNITY_Community 13|Community 13]]
- [[_COMMUNITY_Community 14|Community 14]]
- [[_COMMUNITY_Community 15|Community 15]]
- [[_COMMUNITY_Community 16|Community 16]]
- [[_COMMUNITY_Community 19|Community 19]]
- [[_COMMUNITY_Community 20|Community 20]]
- [[_COMMUNITY_Community 21|Community 21]]
- [[_COMMUNITY_Community 22|Community 22]]
- [[_COMMUNITY_Community 23|Community 23]]
- [[_COMMUNITY_Community 24|Community 24]]
- [[_COMMUNITY_Community 25|Community 25]]
- [[_COMMUNITY_Community 26|Community 26]]
- [[_COMMUNITY_Community 27|Community 27]]
- [[_COMMUNITY_Community 28|Community 28]]
- [[_COMMUNITY_Community 29|Community 29]]
- [[_COMMUNITY_Community 31|Community 31]]
- [[_COMMUNITY_Community 32|Community 32]]
- [[_COMMUNITY_Community 33|Community 33]]
- [[_COMMUNITY_Community 34|Community 34]]
- [[_COMMUNITY_Community 35|Community 35]]
- [[_COMMUNITY_Community 36|Community 36]]
- [[_COMMUNITY_Community 37|Community 37]]
- [[_COMMUNITY_Community 38|Community 38]]

## God Nodes (most connected - your core abstractions)
1. `PostgresSheetReplicaImporter` - 42 edges
2. `Booking` - 32 edges
3. `HttpRoutesTest` - 28 edges
4. `ServicesController` - 17 edges
5. `MockGasStreamWrapper` - 16 edges
6. `Service` - 14 edges
7. `StaffMember` - 12 edges
8. `AppFactory` - 11 edges
9. `BookingController` - 11 edges
10. `GasSyncResult` - 10 edges

## Surprising Connections (you probably didn't know these)
- None detected - all connections are within the same source files.

## Communities (46 total, 27 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.05
Nodes (5): Booking, BookingId, BookingStatus, BookingTest, CreateBooking

### Community 1 - "Community 1"
Cohesion: 0.1
Nodes (4): ConnectionFactory, AppFactory, HttpRoutesTest, MockGasStreamWrapper

### Community 3 - "Community 3"
Cohesion: 0.06
Nodes (6): CreateService, Service, ServiceDuration, ServiceId, ServiceName, ServiceTest

### Community 4 - "Community 4"
Cohesion: 0.07
Nodes (6): CreateStaffMember, StaffDisplayName, StaffId, StaffMember, StaffMemberTest, StaffRole

### Community 6 - "Community 6"
Cohesion: 0.12
Nodes (15): Apps Script / GAS, code:bash (docker compose up --build), code:bash (curl -X POST http://localhost:8080/api/v1/agents/chat \), code:bash (curl -X POST http://localhost:8080/api/v1/services \), code:bash (curl -X POST http://localhost:8080/api/v1/team \), code:bash (curl -X POST http://localhost:8080/api/v1/bookings \), code:powershell (.\tools\migrate.ps1), code:bash (curl -X POST http://localhost:8080/api/v1/sync/sheets/import) (+7 more)

### Community 7 - "Community 7"
Cohesion: 0.13
Nodes (14): Application, code:text (qs-manager-v2/), code:mermaid (flowchart TD), Decision de stack, Domain, Estructura propuesta, Flujo local inicial, Infrastructure (+6 more)

### Community 9 - "Community 9"
Cohesion: 0.15
Nodes (12): 1. Test Suite Execution Commands, 2. Test Tiers Summary Table, 3. Checklist of Features Covered, 4. Test Execution Results, Backend Integration Tests (PHPUnit), Backend Validation & Controller Integration (R2), code:bash (# Run all backend tests, including the new integration and v), code:bash (# Navigate to the E2E tests directory) (+4 more)

### Community 15 - "Community 15"
Cohesion: 0.25
Nodes (7): filterStatus, header, nameInput, nextBtn, perPageSelect, syncButton, toast

### Community 16 - "Community 16"
Cohesion: 0.25
Nodes (7): Architecture, Code Layout, Interface Contracts, Milestones, Project: QS Manager V2 Sincronizar Button & Audit, Scope: Milestone 1, WebUI ↔ API

## Knowledge Gaps
- **40 isolated node(s):** `header`, `filterStatus`, `perPageSelect`, `nextBtn`, `nameInput` (+35 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **27 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Booking` connect `Community 0` to `Community 12`?**
  _High betweenness centrality (0.014) - this node is a cross-community bridge._
- **Why does `Service` connect `Community 3` to `Community 11`?**
  _High betweenness centrality (0.007) - this node is a cross-community bridge._
- **Are the 5 inferred relationships involving `Booking` (e.g. with `.execute()` and `.fromRow()`) actually correct?**
  _`Booking` has 5 INFERRED edges - model-reasoned connections that need verification._
- **What connects `header`, `filterStatus`, `perPageSelect` to the rest of the system?**
  _40 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.05 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.1 - nodes in this community are weakly interconnected._
- **Should `Community 3` be split into smaller, more focused modules?**
  _Cohesion score 0.06 - nodes in this community are weakly interconnected._