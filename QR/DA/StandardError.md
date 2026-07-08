#### [Back to DA contents](_Contents.md)

##### Standard Error of the Mean (SEM)  
`SE = σ / √n`     
where 
σ: Population standard deviation 
n: Sample size.   
If σ is unknown, use the sample standard deviation (s): `SE = s / √n`

`σ = √(Σ(xᵢ - μ)² / N)`
where   
xᵢ: Each individual value in the population   
μ: Population mean   
N: Total number of observations in the population   

`s = √(Σ(xᵢ - x̄)² / (n - 1))`
where  
xᵢ: Each individual value in the sample    
x̄: Sample mean   
n: Sample size    
(n - 1): Bessel's correction (to account for bias in small samples)    

##### Standard Error of a Proportion 
`SE = √(p * (1 - p) / n)`     
where     
p: Sample proportion (e.g., 0.6 for 60%)      
n: Sample size

##### Standard Error of the Difference Between Two Means
`SE = √((σ₁² / n₁) + (σ₂² / n₂))`
where   
σ₁, σ₂: Population standard deviations of the two groups   
n₁, n₂: Sample sizes of the two groups   
If σ is unknown, use sample standard deviations (s₁, s₂):    
`SE = √((s₁² / n₁) + (s₂² / n₂))`

##### Standard Error of the Difference Between Two Proportions
`SE = √((p₁ * (1 - p₁) / n₁) + (p₂ * (1 - p₂) / n₂))`
where   
p₁, p₂: Sample proportions of the two groups   
n₁, n₂: Sample sizes of the two groups  

##### Absolute Sampling Error (ASE) or Margin of Error (MoE)
`ASE =  |x̄ - μ|`    
or   
`ASE =  |p̂ - p|`    
where    
x̄: Sample mean   
μ: True population mean   
p̂: Sample proportion   
p: True population proportion   

`MoE (or ASE) = z x SE`
where   
z is z-score.   
| Confidence Level | Alpha (α) / Tail Error | z-score (Two-Tailed) |
| --- | --- | --- |
| 90% | 0.10 | 1.645 |
| 95% | 0.05 | 1.96 |
| 99% | 0.01 | 2.576 |
| ... | ... | ... |

##### Confidence Interval
`Confidence Interval = Sample Statistic +/- Absolute Sampling Error`