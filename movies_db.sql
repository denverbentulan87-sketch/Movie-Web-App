-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 14, 2026 at 09:13 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `movies_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `movie_watchlist`
--

CREATE TABLE `movie_watchlist` (
  `watchlist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `movie_title` varchar(255) NOT NULL,
  `genre` varchar(100) DEFAULT NULL,
  `status` enum('Watching','Watched','Plan to Watch') DEFAULT 'Plan to Watch',
  `rating` int(11) DEFAULT 0 CHECK (`rating` between 0 and 5),
  `description` text DEFAULT NULL,
  `cover_url` varchar(500) DEFAULT NULL,
  `date_added` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `movie_watchlist`
--

INSERT INTO `movie_watchlist` (`watchlist_id`, `user_id`, `movie_title`, `genre`, `status`, `rating`, `description`, `cover_url`, `date_added`) VALUES
(2, 1, 'One Piece', 'Anime', 'Plan to Watch', 0, 'Monkey D. Luffy sails with his crew of Straw Hat Pirates through the Grand Line to find the treasure One Piece and become the new king of the pirates.', 'assets/covers/cover_6a0561feb72501.50779968.webp', '2026-05-14 13:47:42'),
(3, 1, 'Spider Man 2', 'Fiction', 'Watching', 4, '', 'assets/covers/cover_6a0562771c4d17.34960848.webp', '2026-05-14 13:49:43'),
(4, 1, 'Swapped', 'Fantasy', 'Watched', 5, 'Its very nice. I learned a lot watching the movie..', 'assets/covers/cover_6a0562d33c3d66.01764043.webp', '2026-05-14 13:51:15'),
(5, 1, 'Ong Bak 2', 'Action', 'Watching', 3, '', 'assets/covers/cover_6a056386091589.65830685.webp', '2026-05-14 13:54:14'),
(6, 1, 'Venom 2', 'Sci-Fi', 'Watching', 5, '', 'assets/covers/cover_6a0563d9ea4bb1.55151982.webp', '2026-05-14 13:55:37'),
(8, 1, 'Jujutsu Kaisen', 'Anime', 'Watching', 5, '', 'assets/covers/cover_6a0564c7a8d209.63758661.webp', '2026-05-14 13:59:35'),
(9, 1, 'Avengers', 'Fiction', 'Watching', 5, '', 'assets/covers/cover_6a056504f3fb99.15664705.webp', '2026-05-14 14:00:37'),
(11, 1, 'Vince & Kath & James', 'Drama', 'Watching', 0, '', 'assets/covers/cover_6a05659c091569.53815779.webp', '2026-05-14 14:03:08'),
(12, 1, 'The Conjuring', 'Horror', 'Watching', 5, '', 'assets/covers/cover_6a0565ce8decb8.33079409.webp', '2026-05-14 14:03:58'),
(13, 1, 'Home Alone', 'Comedy', 'Watching', 5, '', 'assets/covers/cover_6a056607c83fb7.32940235.webp', '2026-05-14 14:04:55'),
(14, 1, 'Amazing Earth', 'Documentary', 'Watching', 5, '', 'assets/covers/cover_6a05663d8fc822.45928470.webp', '2026-05-14 14:05:49'),
(15, 1, 'Fifty Shades of Grey', 'Romance', 'Watching', 5, 'HAHAHAHAHH', 'assets/covers/cover_6a0566ab5cead4.60267587.webp', '2026-05-14 14:07:39'),
(17, 1, 'Snow White', 'Fantasy', 'Plan to Watch', 0, '', 'assets/covers/cover_6a056ec363e304.16195917.webp', '2026-05-14 14:42:11'),
(18, 1, 'Avatar:The Last Airbender', 'Fiction', 'Plan to Watch', 0, ' The Last Airbender movie scheduled for 2026.  The major 2026 film project is an animated feature titled The Legend of Aang: The Last Airbender (also referred to as Avatar Aang: The Last Airbender), produced by Avatar Studios and Nickelodeon.', 'assets/covers/cover_6a056f90ea2bc8.29345493.webp', '2026-05-14 14:45:36'),
(19, 1, 'Princess Mononoke', 'Anime', 'Plan to Watch', 0, '', 'assets/covers/cover_6a05751d527439.12932311.webp', '2026-05-14 15:09:17'),
(20, 1, 'Chainsaw Man - The Movie: Reze Arc', 'Anime', 'Watched', 5, '', 'assets/covers/cover_6a0575749e1b46.85467794.webp', '2026-05-14 15:10:44');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `created_at`) VALUES
(1, 'Denver Bentulan', '$2y$10$j5kblHEyGi2q4dBkNxOA3.DlMXhgYOTV61V14qBKCc7NmJS3YQvXu', '2026-05-14 13:35:10'),
(2, 'Juan Dela Cruz', '$2y$10$FNxLXYIZEkW3Axm.PWgoEu/lwPaYIquBqo43BQ3vjzyhXdXmNahYO', '2026-05-14 14:35:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `movie_watchlist`
--
ALTER TABLE `movie_watchlist`
  ADD PRIMARY KEY (`watchlist_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `movie_watchlist`
--
ALTER TABLE `movie_watchlist`
  MODIFY `watchlist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `movie_watchlist`
--
ALTER TABLE `movie_watchlist`
  ADD CONSTRAINT `movie_watchlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
