#### [Back to ML contents](_Contents.md)

#### Types of ML
---
| Type | Data&nbsp;Required | Feedback | Goal |
|-----------|------|------|------|
| Supervised | Labeled (Input + Output) | Direct correction | Predict outputs for new data |
| Unsupervised | Unlabeled (Input only) | None | Discover hidden patterns or groupings |
| Reinforcement	| Sequential actions + Rewards | Rewards/Penalties | Learn the best strategy (policy) for maximum reward |
| Semi-Supervised |	Mostly unlabeled, some labeled	| Partial correction |	Improve accuracy when labeling is too expensive |

Based on the learning type, we call __Supervised Learning Models__, __Unsupervised Learning Models__, etc. In addition, **Self-Supervised Learning Model** is a type of unsupervised learning where the model generates its own labels from the data. **Ensemble Model** is a combined type of multiple models to improve performance (e.g., accuracy, robustness).

It is better to clearly understand the similarities and differences between the terms. All **ML** is **AI**, but not all **AI** is **ML**. **AI** includes non-ML approaches (e.g., symbolic AI, rule-based systems). **ML** is the data-driven branch of AI. **Models** are the "engines" of ML/AI. Modern AI (e.g., LLMs) blurs lines using different ML techniques.

Mixing up the ideas might be helpful to compare and contrast them.

#### Types of AI & ML Models
---
| AI Model Type | ML Model Type | Example |
| --- | --- | --- |
| Rule-Based | **Not ML**)** | Expert systems |
| ML Models | Supervised/Unsupervised/RL | Logistic Regression, K-Means, DQN |
| Deep Learning Models | Supervised/Unsupervised/RL | CNNs, RNNs, Transformers |
| Generative AI Models | Unsupervised/Self-Supervised | GANs, VAEs, LLMs |
| Reasoning Models | Supervised/Self-Supervised | LLMs with chain-of-thought prompting |
| Hybrid Models | Combines ML + Rule-Based | Neuro-symbolic AI |

#### Sub-types of ML
In addition, there are also sub-types of learning as follows:
| Category | Subtype | Example | Requires Labels? | Key Trait |
| --- | --- | --- | --- | --- |
| Supervised | Classification | Logistic Regression | ✅ Yes | Predicts discrete labels. |
| Supervised | Regression | Linear Regression | ✅ Yes | Predicts continuous values. |
| Unsupervised | Clustering | K-Means | ❌ No | Finds groups in data. |
| Unsupervised | Dimensionality Reduction | PCA | ❌ No | Reduces data complexity. |
| Semi-Supervised | Self-Training | Pseudo-labeling | ✅ (Small) + ❌ | Uses both labeled and unlabeled data. |
| Self-Supervised | Contrastive Learning | SimCLR, MoCo | ❌ No | Learns by comparing examples. |
| Self-Supervised | Masked Language Modeling | BERT | ❌ No | Generates its own labels. |
| Reinforcement Learning | Q-Learning | DQN | ❌ No | Learns from rewards/penalties. |
| Transfer Learning | Fine-Tuning | Pre-trained LLMs | ✅ (Target task) | Adapts pre-trained models. |

#### Specialised approaches
In addition to those core type and sub-types, there are still specialised types as follows:
| Category | Type or Subtype? | Relationship to Core Types | Example |
| --- | --- | --- | --- |
| Active Learning | Specialized Approach | Can be applied to supervised or semi-supervised learning (focuses on label acquisition). | Query-by-committee |
| Transfer Learning | Specialized Approach | Works with supervised, unsupervised, or self-supervised models (reuses pre-trained knowledge). | Fine-tuning BERT for Q&A |
| Few-Shot Learning | Specialized Approach | Often used in supervised or self-supervised contexts (learns from minimal examples). | Meta-learning (MAML) |
| Zero-Shot Learning | Specialized Approach | Typically supervised or self-supervised (generalizes without task-specific labels). | LLMs answering unseen questions |
| Online Learning | Specialized Approach | Can be supervised, unsupervised, or reinforcement (learns incrementally from streaming data). | Incremental SVM |
| Federated Learning | Specialized Approach | Applies to supervised or unsupervised models (trains across decentralized devices). | Google’s Gboard |
| Imitation Learning | Specialized Approach | Often reinforcement learning (learns from expert demonstrations instead of rewards). | Behavioral cloning |

#### Techniques
Again, the types shouldn't be confused with techniques. For example, the following are AI techniques:
| Technique | Purpose | Example |
| --- | --- | --- |
| Chain of Thought | Improve reasoning by generating intermediate steps. | "Let’s solve this math problem step by step." |
| Few-Shot Prompting | Enable the model to perform tasks with a few examples. | "Here are 3 examples of how to translate sentences. Now translate this new sentence." |
| Zero-Shot Prompting | Enable the model to perform tasks without any examples. | "Translate this sentence to French." (no examples provided) |
| Fine-Tuning | Adapt a pre-trained model to a specific task using labeled data. | Fine-tuning BERT for medical Q&A. |
| In-Context Learning | Provide the model with context (e.g., examples or instructions) in the prompt. | "Answer the following question using the provided context." |

And the following are ML techniques:
| Category | Technique | Purpose | Example Use Case |
| --- | --- | --- | --- |
| Training | Supervised Learning | Train models on labeled data to predict outputs. | Classification, regression. |
| Training | Unsupervised Learning | Train models on unlabeled data to find patterns. | Clustering, dimensionality reduction. |
| Training | Semi-Supervised Learning | Train models on a mix of labeled and unlabeled data. | Medical image analysis. |
| Training | Reinforcement Learning | Train models using rewards/penalties for sequential decision-making. | Robotics, gaming (e.g., AlphaGo). |
| Training | Self-Supervised Learning | Train models by generating labels from the data itself. | Pre-training language models (e.g., BERT). |
| Training | Transfer Learning | Reuse a pre-trained model for a new, related task. | Fine-tuning a pre-trained CNN for a new classification task. |
| Training | Active Learning | Query a human for labels on uncertain examples to reduce labeling costs. | Drug discovery, rare disease diagnosis. |
| Training | Federated Learning | Train models across decentralized devices without sharing raw data. | Privacy-preserving mobile keyboard suggestions. |
| Training | Imitation Learning | Train models to mimic expert behavior using demonstrations. | Autonomous driving, robotics. |
| Training | Online Learning | Continuously update the model as new data arrives. | Real-time recommendation systems. |
| Training | Few-Shot Learning | Train models to perform tasks with only a few labeled examples. | Meta-learning (e.g., MAML). |
| Training | Zero-Shot Learning | Enable models to perform tasks without any task-specific labeled examples. | LLMs answering questions about unseen topics. |
| --- | --- | --- | --- |
| Optimization | Gradient Descent | Minimize the loss function by iteratively adjusting model parameters. | Training neural networks. |
| Optimization | Stochastic Gradient Descent (SGD) | Optimize models using a random subset of data in each iteration. | Large-scale training. |
| Optimization | Adam | Adaptive optimization algorithm combining momentum and RMSprop. | Training deep learning models. |
| Optimization | RMSprop | Adaptive learning rate optimization for neural networks. | Training RNNs. |
| Optimization | Batch Normalization | Normalize layer inputs to stabilize and accelerate training. | Deep neural networks. |
| Optimization | Dropout | Randomly deactivate neurons during training to prevent overfitting. | Neural networks. |
| Optimization | Regularization (L1/L2) | Penalize large weights to prevent overfitting. | Linear regression, neural networks. |
| --- | --- | --- | --- |
| Evaluation | Cross-Validation | Evaluate model performance by splitting data into training and validation sets. | Hyperparameter tuning. |
| Evaluation | Confusion Matrix | Evaluate classification models by summarizing true/false positives/negatives. | Binary/multi-class classification. |
| Evaluation | Precision/Recall/F1-Score | Measure classification performance. | Spam detection. |
| Evaluation | ROC-AUC | Evaluate classification models by plotting the trade-off between true positive and false positive rates. | Medical testing. |
| Evaluation | Mean Squared Error (MSE) | Measure regression model accuracy. | House price prediction. |
| Evaluation | Accuracy | Measure the proportion of correct predictions. | Classification tasks. |
| --- | --- | --- | --- |
| Data Preprocessing | Normalization | Scale features to a standard range (e.g., 0 to 1 or -1 to 1). | Preparing data for neural networks. |
| Data Preprocessing | Standardization | Transform features to have a mean of 0 and a standard deviation of 1. | Preparing data for SVM or k-NN. |
| Data Preprocessing | One-Hot Encoding | Convert categorical variables into binary vectors. | Preparing categorical data for ML models. |
| Data Preprocessing | Feature Engineering | Create new features from raw data to improve model performance. | Extracting features from text or images. |
| Data Preprocessing | Data Augmentation | Generate new training examples by applying transformations to existing data. | Image classification (e.g., flipping, rotating images). |
| Data Preprocessing | Dimensionality Reduction | Reduce the number of features while preserving information. | PCA, t-SNE for visualization. |
| --- | --- | --- | --- |
| Model-Specific | Backpropagation | Algorithm for training neural networks by propagating errors backward. | Training deep learning models. |
| Model-Specific | Attention Mechanisms | Enable models to focus on relevant parts of the input. | Transformers (e.g., BERT, GPT). |
| Model-Specific | Convolutional Layers | Extract spatial features from input data (e.g., images). | CNNs for image recognition. |
| Model-Specific | Recurrent Layers | Process sequential data by maintaining a hidden state. | RNNs/LSTMs for time-series or text. |
| Model-Specific | Ensemble Methods | Combine multiple models to improve performance. | Random Forest, XGBoost, Stacking. |