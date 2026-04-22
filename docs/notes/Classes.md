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
#### methods
- login()
- logout()
- signup()
- updateProfile()
- view_trip()

## Member (User)
#### methods
- addActivity()
- rsvpActivity()
- logExpense()
- castVote()
- assignSharedItem()
- updateHealthInfo()
- inviteMembers()
- approveSettlement()

## Trip Leader (Member)
#### methods
- revertVersion()
- confirmActivity()
- editPermissions()

## Trip
#### attributes
- tripID: int
- destination: string
- startDate: date
- endDate: date
- currency: string
- budgetLimit: float
- createdBy: int
- status: string
#### methods
- createTrip()
- deleteTrip()
- addMember()
- removeMember()

## Itinerary
#### attributes
- itineraryID: int
- tripID: int
- activities: Activity[]
#### methods
- conflictDetection()
- routeOptimizer()
-  timezoneNormalizer()
- realTimeSync()
- transporationBuffer()
- saveVersion()

## Activity
#### attributes
- activityID: int
- itineraryID: int
- name: string
- location: string
- dateTime: datetime
- status: string
- modeTransport: string
#### methods
- createActivity()
- editActivity()
- deleteActivity()
- trackRSVP()
 
## Itinerary Version
#### attributes
- versionID: int
- itineraryID: int
- changedBy: int 
- dateCreated: date
- snapshot: JSON
#### methods
- saveSnapshot()
- restoreSnapshot()

## Expense
#### attributes
- expenseID: int
- tripID: int
- name: string
- type: string (food/transport/activities/groceries)
- amount: float
- currency: string
- paidBy: int (userID)
#### methods
- convertCurrency()
- splitExpense()

## Poll
#### attributes
- pollID: int
- tripID: int
- question: string
- options: string[]
- isAnonymous: boolean
- deadline: date
- status: string
- type: string (standard/priority)
#### methods
- createPoll()
- closePoll()
- getResults()

## Vote
#### attributes
- voteID: int
- pollID: int
- userID: int
- selectedOption: string
#### methods 
- castVote() 

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
- uploadDocument()
- deleteDocument()
- checkVisa()

## Notification
#### attribute 
- notifID: int
- tripID: int
- userID: int
- message: string
- type: string (budget_alert / daily_briefing)
#### methods
- exceedsBudget()
- GenerateDailyBrief()