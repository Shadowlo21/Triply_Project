# Triply
# Collaborative Itinerary & Group Travel Planner 
- Example(s): https://wanderlog.com/ | https://www.tripit.com/

## Project Overview
The project brief describes Itinerary Travel as more than a booking site — it's a **real-time collaborative workspace** for groups. The brief frames the problem as solving the "logistical nightmare" of group travel, and it breaks the entire travel experience into three phases:

**Inspiration Phase** — the group hasn't decided where to go yet. Members vote on destinations, pin ideas to a mood board, and build consensus before anything is locked in.

**Execution Phase** — the trip is happening. Members track the itinerary in real time, see live updates, and coordinate logistics on the ground.

**Settlement Phase** — the trip is over. The system calculates who owes whom and minimizes the number of transactions needed to clear all debts.

---
## The 4 Core Modules

### 1. Collaborative Itinerary (Travel Program/Schedule) Module:
This manages the **time** and **space** organization of the trip. The setup of the trip with a shared calendar/schedule that multiple users can edit simultaneously. It handles things like
scheduling activities, detecting time conflicts, ordering activities by location and tracking who changed what. 
### 2. Financial Settlement Module:
This manages the **Financial expenses** across the whole trip, handles multiples currencies (Egyptian Pounds, Dollars, Euros), supports uneven splits, at the end runs an algorithm to figure out the fewest possible transactions to settle all debts.
### 3. Document & Logistics Vault
A file storage system for travel documents like PDF tickets for members, passport scans. An important feature is **permission-based access**, where passport scan should only be visible to its owner and the trip organizer, not the whole group.
### 4. Social Consensus (Agreement By whole group) Module:
This handles group decision-making. Before the group commits to anything , they need to all agree on it. This module covers polls, voting (including a weighted vote for the organizer to have tie-breaking vote), deadlines that auto-close votes, anonymous voting for sensitive decisions like budget caps.