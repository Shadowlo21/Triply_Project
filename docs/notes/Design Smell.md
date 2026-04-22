# What is a design smell?
a design smell is a structure in a design that indicates a violation of fundamental design principles and can negatively impact the project's quality but doesn't break the project's functionality. It indicates poor design that reduces clarity and scalability over time.

## Smell in our design
The `Member` Class is flagged as a **God** class since it interacts with expenses, votes, activities, health info and shared items all at once which means it holds too many responsibilities which in the end becomes really hard to modify and extend without too much hassle.
# How to avoid it
Apply the **Single Responsibility Principle** that says, a class should only have one responsibility and split the responsibilities into other relevant classes for ex. `UserProfile` class which handles personal attributes. This makes every class smaller and more manageable for each functionality it is responsible for.