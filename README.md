### University Club Management system.

To use it (from cli)

- #### Step 0: YOU MUST SETUP MYSQL DATABASE (the solution provided here includes the run instruction from command prompt/terminal. You may use xampp) AND THEN MODIFY THE "config.php" on "config" folder. 
- Step 1: Open a database named <i> club_management </i>
- Step 2: run the command from terminal:
```
mysql -u root -p club_management < ./config/club.sql
```
- Step 3: run php server from root folder by:
```
php -S localhost:8000
```