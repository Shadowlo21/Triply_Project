User ──▷ Member              : Inheritance
Member ──▷ TripLeader        : Inheritance
User ──▷ Admin               : Inheritance

Trip ◆── Itinerary           : Composition  1 to 1
Itinerary ◆── Activity       : Composition  1 to *
Itinerary ◆── ItineraryVersion : Composition 1 to*
Trip ◆── Expense             : Composition  1 to *
Trip ◆── Poll                : Composition  1 to*
Poll ◆── Vote                : Composition  1 to *
Trip ◆── Document            : Composition  1 to*
Trip ◆── Notification        : Composition  1 to *
Trip ◆── Emergency           : Composition  1 to*
Trip ◆── Invitation          : Composition  1 to *

Member ── MemberTrip         : Association  1 to *
Trip ── MemberTrip           : Association  1 to*

Member ── Expense            : Association  1 to *
Member ── Document           : Association  1 to*
Member ── Activity           : Association  1 to *
Member ── Invitation         : Association  1 to*
