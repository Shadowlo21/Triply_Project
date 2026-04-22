## User << abstract >>
#### attributes
- userID: int
- name: string
- email: string
- password: string
- phoneNumber: string
- emergencyContact: string 
- nationality: string
- points: int
- isActive :boolean 
#### methods
- login(email: string, password: string) : boolean
- logout(): void
- signup(name: string, email: string, password, phone: string): void
- updateProfile(name: string, phone: string, emergency: string): void
- viewTrip(tripID: int) : Trip

## Member (User)
#### methods
- addActivity(itineraryID: int, name: string, location: string, dateTime: datetime, duration: int, transport: string): void
- rsvpActivity(activityID: int, status: string): void
- logExpense(tripID: int, name: string, amount: float, currency: string, type: string, paraticipants: int[], splitType: string): void
- castVote(pollID: int, option: string): void
- updateHealthInfo(info: string): void
- inviteMember(tripID: int, email: string): void
- approveSettlement(tripID: int): boolean

## Trip Leader (Member)
#### methods
- revertVersion(versionID: int): void
- confirmActivity(activityID: int, status: string): void
- editPermissions(userID: int, role: string): void

## Admin (User)
#### methods
- manageUsers(userID: int): User
- suspendUser(userID): void
- manageTrips(tripID: int): Trip

## Member Trip
#### attributes
- membershipID: int
- tripID: int
- userID: int
- role: string
- joinedAt: datetime
#### methods
- join(tripID: int, userID: int, role: string): void
- leave(tripID: int, userID: int): void

## Trip
#### attributes
- tripID: int
- itinerary: Itinerary()
- expense: Expense[]
- destination: string
- startDate: date
- endDate: date
- baseCurrency: string
- budgetLimit: float
- createdBy: int
- status: string 
#### methods
- createTrip(destination: string, startDate: date, endDate: date, currency: string, budget: float): Trip
- deleteTrip(tripID: int): void
- addMember(userID: int): void
- removeMember(userID: int)

## Itinerary
#### attributes
- itineraryID: int
- tripID: int
- itineraryVersion: ItineraryVersion()
- activities: Activity[]
#### methods
- conflictDetection(activityID: int): boolean
- routeOptimizer(): Activity[]
-  timezoneNormalizer(dateTime: datetime, location: string): datetime
- realTimeSync(): void
- transporationBuffer(activityID: int, transport: string): int
- saveVersion(changedBy: int): void

## Activity
#### attributes
- activityID: int
- itineraryID: int
- name: string
- location: string
- dateTime: datetime 
- duration: int
- status: string 
- modeOfTransport: string
- createdBy : int
#### methods
- createActivity(name: string, location: string, dateTime: datetime, duartion: int, transport: string)
- editActivity(activityID: int, name: string, location: string, dateTime: datetime): void
- deleteActivity(activityID: int) : void
- trackRSVP(userID: int, status: string) : void
 
## Itinerary Version
#### attributes
- versionID: int
- itineraryID: int
- changedBy: int 
- dateCreated: date
- snapshot: JSON
#### methods
- saveSnapshot(itineraryID: int, changedBy: int) : void
- restoreSnapshot(versionID: int) : Itinerary

## Expense
#### attributes
- expenseID: int
- tripID: int
- name: string
- type: string (food/transport/activities/groceries)
- amount: float
- currency: string
- paidBy: int (userID)
- participants: int[] 
- splitType: string
- dataLogged: datetime
#### methods
- convertCurrency(amount: float, from: string, to: string) : float
- splitExpense(amount: float, participants: int[], splitType: string) : map

## Poll
#### attributes
- pollID: int
- tripID: int
- vote: Vote[]
- question: string
- options: string[]
- isAnonymous: boolean
- deadline: date
- status: string (Open, Closed)
- type: string (standard/priority)
#### methods
- createPoll(tripID: int, question: string, options: string[], deadline: datetime, isAnonymous: boolean, type: string) : void
- closePoll(pollID: int) : void
- getResults(pollID: int) : string

## Vote
#### attributes
- voteID: int
- pollID: int
- userID: int
- selectedOption: string
#### methods 
- castVote(pollID: int, userID: int, option: string): void 

## Document
#### attributes
- name: string
- docID: int
- userID: int
- tripID: int
- filePath: string
- visibility: string 
- uploadDate: date
#### methods
- uploadDocument(tripID: int, name: string, filePath: string, visibility: string) : void
- deleteDocument(docID: int) : void
- checkVisa(nationality: string, destination: string) : boolean

## Notification
#### attribute 
- notifID: int
- tripID: int
- userID: int
- message: string
- type: string (budget_alert / daily_briefing)
- isRead: boolean
- createdAt: datetime

#### methods
- exceedsBudget(tripID: int, totalSpent: float) : void
- generateDailyBriefing(tripID: int) : void


## Emergency
#### attribute 
- emergencyID: int
- tripID: int
- triggeredBy: int (userID)
- message: string
- timestamp: datetime
#### methods
- broadcastEmergency(tripID: int, triggeredBy: int, message: string) : void
### Invitation
### attributes
- invitationID: int
- tripID: int
- invitedBy: int
- invitedUserEmail: string
- inviteToken: string (random numbers generated)
- status: string
### methods
- sendInvitation(tripID: int, invitedBy: int, email: string) : void
- acceptInvitation(token: string) : void
- rejectInvitation(token: string) : void

